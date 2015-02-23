<?php
/*
	OpenNewsletter php-api
	Description : Manage a newsletter
	Dependencies :
		NemesisFramework/core
			core/class.Loader.php
			core/class.URL.php
			core/class.Router.php
			core/class.Session.php
			core/class.Api.php
			core/functions.php
*/

include_once 'core/bootstrap.php';
core_functions();
core_autoloader();

$Loader = Loader::getInstance();
$Loader->initClass('Router');

class Newsletter
{

	private $config;

	private $db = null;
	private $dbQuery = '';
	private $dbResult = array();

	private $session = null;

	public function __construct()
	{
		$this->config = json_decode(file_get_contents(NEMESIS_PATH.'config.json'), true);
	}

	public function login()
	{
		if ($this->isAdminSession())
			Api::error('An admin session already exists');

		if ($_SERVER['REQUEST_METHOD'] == 'GET')
		{
			$errors = array();

			if (!isset($_REQUEST['user']))
				$errors[] = array('field' => 'user', 'message' => 'User can\'t be blank');

			if (!isset($_REQUEST['pwd']))
				$errors[] = array('field' => 'pwd', 'message' => 'Password can\'t be blank');

			if (sizeof($errors) > 0)
				Api::error('Blank field', $errors);

			if (($_REQUEST['user'] != $this->config['admin']['user']) || ($_REQUEST['pwd'] != $this->config['admin']['password']))
				Api::error('Wrong user or password');

			$this->createAdminSession();
			Api::success();
		}
	}

	public function logout()
	{
		$this->session = new Session();
		$this->session->kill('newsletter');
		Api::success();
	}

	private function isAdminSession ()
	{
		$this->session = new Session();
		return $this->session->check('newsletter', $this->config['admin']['user'].'|'.$this->config['admin']['password']);
	}

	private function createAdminSession ()
	{
		if (!$this->session)
			$this->session = new Session();

		$this->session->secure('newsletter', $this->config['admin']['user'].'|'.$this->config['admin']['password']);
	}

	private function dbQuery ($dbQuery, $params=array(), $transactionFinalQuery=1)
	{
		try
		{
			if (!$this->db)
			{
				$this->db = new PDO("sqlite:".NEMESIS_PATH."newsletter.sqlite");
				$this->db->beginTransaction();
			}

			$this->dbResult[] &= $dbResult = $this->db->prepare($dbQuery);
			if ($dbResult)
				$dbResult->execute($params);

			if ($transactionFinalQuery)
				$this->db->commit();
		}
		catch(PDOException $e)
		{
			$this->db->rollBack();
			Api::error($e->getMessage());
		}
	}

	public function install ()
	{
		if (!$this->isAdminSession())
			Api::unauthorized();

		$structure = '
		CREATE TABLE IF NOT EXISTS subscribers (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
		 	email TEXT UNIQUE
		);
		CREATE TABLE IF NOT EXISTS posts (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			date INTEGER,
			sending INTEGER,
			object TEXT,
			body TEXT
		);
		';

		$this->dbQuery($structure);
		Api::success();
	}

	public function subscribers ()
	{
		$email = Api::getNextHash();

		if (!$email)
		{
			if (!$this->isAdminSession())
				Api::unauthorized();

			switch($_SERVER['REQUEST_METHOD'])
			{
				case 'GET':
					$this->dbQuery('SELECT email FROM subscribers');
					$subscribers = array();
					while ($this->dbResult[0] && $r=$this->dbResult[0]->fetch())
						$subscribers[] = $r['email'];
					Api::data($subscribers);
				break;

				case 'POST':

					if (!isset($_REQUEST['subscribers']))
						Api::error('$_REQUEST[\'subscribers\'] is empty');

					$subscribers = json_decode($_REQUEST['subscribers']);
					if (($count=count($subscribers)) == 0)
						Api::error('Subscribers list is empty');

					$this->dbQuery('DELETE FROM subscribers; UPDATE SQLITE_SEQUENCE SET seq = 0 WHERE name = \'subscribers\';');
					$this->dbQuery('SELECT email FROM subscribers');

					for ($i=0; $i < $count-1; $i++)
					{
						if (is_email($subscribers[$i]))
							$this->dbQuery('INSERT INTO subscribers (email) VALUES (:email)', array(':email' => $subscribers[$i]), 0);
					}

					if (is_email($subscribers[$count-1]))
						$this->dbQuery('INSERT INTO subscribers (email) VALUES (:email)', array(':email' => $subscribers[$i]));

					Api::success();
				break;

			}

		}

		if (!$email || ($email && !is_email($email)))
			Api::error('Wrong email');

		switch($_SERVER['REQUEST_METHOD'])
		{
			case 'POST':

				$this->dbQuery('SELECT id FROM subscribers WHERE email = :email', array(':email' => $email));
				if ($this->dbResult[0] && $this->dbResult[0]->fetch())
					Api::error('Email already exists');

				$this->dbQuery('INSERT INTO subscribers (email) VALUES (:email)', array(':email' => $email));
				Api::success();
			break;

			case 'DELETE':

				$this->dbQuery('SELECT id FROM subscribers WHERE email = :email', array(':email' => $email));
				if (!$this->dbResult[0] || $this->dbResult[0] && !$this->dbResult[0]->fetch())
					Api::error('Email does not exist');

				$this->dbQuery('DELETE FROM subscribers WHERE email = :email', array(':email' => $email));
				Api::success();
			break;
		}
	}

	public function posts ()
	{
			switch ($_SERVER['REQUEST_METHOD'])
			{
				case 'GET' :
					if (isset($_REQUEST['drafts']))
					{
						if (!$this->isAdminSession())
							Api::unauthorized();

						$nbOfItems = Api::getNextHash();
						$this->dbQuery('SELECT * FROM newsletters WHERE date = 0 ORDER BY id DESC:limit', array(':limit' => ($nbOfItems)? ' LIMIT '.$nbOfItems:''));

						if (!$this->dbResult[0] || $this->dbResult[0] && !$r=$this->dbResult[0]->fetchAll(SQLITE_ASSOC))
							Api::error('There is not any newsletter from the database');

						Api::data($r);
					}

					$nbOfItems = Api::getNextHash();
					$this->dbQuery('SELECT * FROM posts WHERE date > 0 AND sending = -1 ORDER BY date DESC:limit', array(':limit' => ($nbOfItems)? ' LIMIT '.$nbOfItems:''));

					if (!$this->dbResult[0] || $this->dbResult[0] && !$r=$this->dbResult[0]->fetchAll(SQLITE_ASSOC))
						Api::error('There is not any newsletter from the database');

					Api::data($r);
				break;

				case 'POST' :
					if (!$this->isAdminSession())
						Api::unauthorized();

					if (!isset($_REQUEST['body']) && !isset($_REQUEST['subject']))
						Api::error('$_REQUEST[\'body\'] and/or $_REQUEST[\'object\'] are missing');

					$this->dbQuery('INSERT INTO posts (object, body, date, sending) VALUES (":object", ":body", 0, -1)', array(':object' => $_REQUEST['object'], ':body' => $_REQUEST['body']));
					Api::success();
				break;

				/*update body and subject*/
				case 'PATH':
					if (!$this->isAdminSession())
						Api::unauthorized();

				break;

				/*send*/
				case 'PUT':
				if (!$this->isAdminSession())
					Api::unauthorized();

				break;

				case 'DELETE' :
					if (!$this->isAdminSession())
						Api::unauthorized();

				break;
			}

	}

}

Api::get('Newsletter');
Api::error404();
