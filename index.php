<?php
/*
	nemesis-api-example
	Description : REST API to manage a newsletter
	Dependencies :
		nemesis-framework/core
			core/class.Loader.php
			core/class.URL.php
			core/class.Router.php
			core/class.Session.php
			core/class.Api.php
			core/functions.php
*/

/*
* Load the Composer autoloader.
*/
if (!file_exists($composerAutoloader='vendor/autoload.php'))
{
  echo 'Composer is not installed. Get it on http://getcomposer.org';
  exit();
}

require $composerAutoloader;

/*
* Load the NemesisFramework boostraper.
*/
get_errors();
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

	public function login()
	{
		if ($this->isAdminSession())
			Api::error('An admin session already exists');

		if ($_SERVER['REQUEST_METHOD'] == 'GET')
		{
			$errors = array();

			if (!isset($_REQUEST['email']))
				$errors[] = array('field' => 'email', 'message' => 'Email can\'t be blank');

			if (!isset($_REQUEST['pwd']))
				$errors[] = array('field' => 'pwd', 'message' => 'Password can\'t be blank');

			if (sizeof($errors) > 0)
				Api::error('Blank field', $errors);

			$this->dbQuery('SELECT email, pwd FROM admins WHERE email = ":email" AND pwd = ":pwd"', array(':email' => $_REQUEST['email'], ':pwd' => $this->pwdHash($_REQUEST['pwd'])));

			if (!$this->dbResult[0] || $this->dbResult[0] && $this->dbResult[0]->fetch())
				Api::error('Wrong email or password');

			$this->createAdminSession($_REQUEST['email'], $_REQUEST['pwd']);
			Api::success();
		}
	}

	public function logout()
	{
		$this->session = new Session();
		$this->session->kill('newsletter');
		Api::success();
	}

	private function isAdminSession ($u='', $p='', $install=0)
	{
		$this->session = new Session();
		return $this->session->check('newsletter'.(($install)? '-install':''));
	}

	private function createAdminSession ($u, $p, $install=0)
	{
		if (!$this->session)
			$this->session = new Session();

		$this->session->secure('newsletter'.(($install)? '-install':''));
	}

	private function dbQuery ($dbQuery, $params=array(), $transactionFinalQuery=1)
	{
		try
		{
			if (!$this->db)
			{
				$this->db = new PDO("sqlite:".NEMESIS_PROCESS_PATH."newsletter.sqlite");
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

	private function pwdHash($pwd)
	{
		//require 'lib/password.php';
		return password_hash($pwd, PASSWORD_BCRYPT, array("cost" => 7, "salt" => "newsletterS"));
	}

	public function install ()
	{

		if ($_SERVER['REQUEST_METHOD'] == 'GET')
		{
			$errors = array();

			if (!isset($_REQUEST['email']))
				$errors[] = array('field' => 'email', 'message' => 'Email can\'t be blank');

			if (sizeof($errors) > 0)
				Api::error('Blank field', $errors);

			if (!is_email($_REQUEST['email']))
				Api::error('Wrong email');

			$errors = array();

			if ($admin=$this->isAdminSession('','',1) && !isset($_REQUEST['pwd']))
				$errors[] = array('field' => 'pwd', 'message' => 'Pwd can\'t be blank');

			if (sizeof($errors) > 0)
				Api::error('Blank field', $errors);

			if (!$admin || !$this->isAdminSession($_REQUEST['email'], $_REQUEST['pwd'], 1))
			{
				$randomPassword = key_generator(8);

				//require 'PHPMailerAutoload.php';
				$mail = new PHPMailer;
				$mail->From = 'noreply@'.URL::$request['DOMAIN'];
				$mail->FromName = 'Newsletter API';
				$mail->addAddress($_REQUEST['email']);
				$mail->Subject = 'Admin Password';
				$mail->AltBody = 'Password to sign in as '.$_REQUEST['email'].' : '.$randomPassword;

				if(!$mail->send())
					Api::error($mail->ErrorInfo);

				$this->createAdminSession($_REQUEST['email'], $randomPassword, 1);
				Api::success();
			}

			$this->session->kill('newsletter-install');
			$this->createAdminSession($_REQUEST['email'], $_REQUEST['pwd']);
		}

		if (!file_exists($db=NEMESIS_PROCESS_PATH.'newsletter.sqlite'))
		{
			chmod(NEMESIS_PROCESS_PATH, 0777);
			touch($db);
			chmod(NEMESIS_PROCESS_PATH, 0755);
		}

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
		CREATE TABLE IF NOT EXISTS admins (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			email TEXT,
			pwd TEXT
		);
		';

		$this->dbQuery($structure);
		$this->dbQuery('INSERT INTO admins (email, pwd) VALUES (":email", ":pwd")', array(':email' => $_REQUEST['email'], ':pwd' => $this->pwdHash($_REQUEST['pwd'])));
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

				$errors = array();

				if (!isset($_REQUEST['body']))
					$errors[] = array('field' => 'body', 'message' => 'body can\'t be blank');

				if (!isset($_REQUEST['object']))
					$errors[] = array('field' => 'object', 'message' => 'object can\'t be blank');

				if (sizeof($errors) > 0)
					Api::error('Blank field', $errors);

				$this->dbQuery('INSERT INTO posts (object, body, date, sending) VALUES (":object", ":body", 0, -1)', array(':object' => $_REQUEST['object'], ':body' => $_REQUEST['body']));
				Api::success();
			break;

			/*update body and subject*/
			case 'PATH':
				if (!$this->isAdminSession())
					Api::unauthorized();

				$errors = array();

				$id = Api::getNextHash();

				if (!$id)
					$errors[] = array('field' => 'id', 'message' => 'id can\'t be blank');

				if (!isset($_REQUEST['body']))
					$errors[] = array('field' => 'body', 'message' => 'body can\'t be blank');

				if (!isset($_REQUEST['object']))
					$errors[] = array('field' => 'object', 'message' => 'object can\'t be blank');

				if (sizeof($errors) > 0)
					Api::error('Blank field', $errors);


				$this->dbQuery('UPDATE posts SET object = ":object", body = ":body" WHERE id = ":id"', array(':id' => $id, ':object' => $_REQUEST['object'], ':body' => $_REQUEST['body']));
				Api::success();
			break;

			/*send*/
			case 'PUT':
				if (!$this->isAdminSession())
					Api::unauthorized();

				$errors = array();

				$id = Api::getNextHash();

				if (!$id)
					$errors[] = array('field' => 'id', 'message' => 'id can\'t be blank');

				if (sizeof($errors) > 0)
					Api::error('Blank field', $errors);

				$this->dbQuery('SELECT object, body FROM posts WHERE id = ":id"', array(':id' => $id), 0);

				if (!$this->dbResult[0] || $this->dbResult[0] && !$p=$this->dbResult[0]->fetch())
					Api::error('This id does not exist', $errors);

				$this->send($id, $p['object'], $p['body']);

			break;

			case 'DELETE' :
				if (!$this->isAdminSession())
					Api::unauthorized();

			break;
		}
	}

	public function settings ()
	{
      /* Not defined yet */
	}

	private function send($id, $object, $body)
	{
		$this->dbQuery('UPDATE posts SET sending = "0" WHERE id = ":id"', array(':id' => $id));


		$this->dbQuery('SELECT id, email FROM subscribers');

		while ($this->dbResult[0] && $r=$this->dbResult[0]->fetch()) {
			//$r['email'], $object, $body;
			//require 'PHPMailerAutoload.php';
			$mail = new PHPMailer;
			$mail->isHTML(true);
			$mail->From = 'noreply@'.URL::$request['DOMAIN'];
			$mail->FromName = 'Newsletter API';
			$mail->addAddress($r['email']);
			$mail->Subject = $object;
			$mail->Body = $body;

			if(!$mail->send())
				Api::error($mail->ErrorInfo);

			$this->dbQuery('UPDATE posts SET sending = ":sid" WHERE id = ":id"', array(':sid' => $r['id'], ':id' => $id));
		}

		$this->dbQuery('UPDATE posts SET sending = -1, date = :now WHERE id = ":id"', array(':now' => time(), ':id' => $id));
	}


}

Api::CORS();
Api::RESTMethods();
Api::get('Newsletter');
Api::error404();
