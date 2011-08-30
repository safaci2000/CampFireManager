#! /usr/bin/php
<?php
echo "Welcome to CampFireManager Installation!\n";

if (!file_exists('./sql')) {
	die("\nInstall files not found. Please run this script from the directory containing CampFireManager\n");
}
if (!is_writable('./db.php')) {
	die("\ndb.php is not writable. Please make sure you have permission to edit this file - you may need to run this script with root privileges\n");
}
if (!file_exists('/usr/share/doc/gammu/examples/sql/mysql.sql.gz')) {
	die("\nGammu install files not found. Please ensure Gammu is installed, at that the database structure can be found at /usr/share/doc/gammu/examples/sql/mysql.sql.gz.\n");
}
$yn = readline("\nWould you like CampFireManager to set up your databases for you? (Y/N)");
switch ($yn) {
	case 'Y':
	case 'y':
		echo "\n";
		$rootpassword = readline("Please enter your MySQL Root password: ");
		if ($rootdb = mysql_connect('localhost', 'root', $rootpassword)) {
			$chars = str_split(sha1(rand()),2);
			foreach ($chars as $k => $char) {
				$c = chr((hexdec($char)/255*95)+31);
				if ($c != "'") {
					$chars[$k] = $c;
				}
			}
			$password = implode($chars);
			$username = 'campfire';
			$database = 'campfire';
			try {
				if(!mysql_query("CREATE USER 'campfire'@'%' IDENTIFIED BY '$password';", $rootdb)) {
					throw new Exception("Couldn't create database user");
				}
				if(!mysql_query("CREATE DATABASE IF NOT EXISTS `campfire`;", $rootdb)) {
					throw new Exception("Couldn't create database");
				}
				if(!mysql_query("GRANT ALL PRIVILEGES ON `campfire` . * TO 'campfire'@'%';", $rootdb) || !mysql_query("GRANT USAGE ON * . * TO 'campfire'@'%' IDENTIFIED BY '$password';", $rootdb)) {
					throw new Exception("Couldn't grant database privileges");
				}
			} catch (Exception $e) {
				die("\n".$e->getMessage()."\n");
			}
			mysql_close($rootdb);
		} else {
			die("\nCould not connect to the databse as the root user\n");
		}
		break;

	case 'N':
	case 'n':
		$username = readline("Please enter your MySQL username: ");
		$password = readline("Please enter your MySQL password: ");
		$database = readline("Please enter the name of your database: ");
		break;
	default:
		die("\nYou must enter Y or N\n");
		break;
}
try {
	$db = mysql_connect('localhost', $username, $password);
	if(!mysql_select_db($database, $db)) {
		throw new Exception("Could't connect to Campfire's database. You may have entered the wrong credentials");
	}
	$sql1 = explode(';', file_get_contents('./sql'));
	$sql2 = explode(';', `gunzip -c /usr/share/doc/gammu/examples/sql/mysql.sql.gz`);
	$sql = array_merge($sql1, $sql2);
	foreach ($sql as $query) {
		try {
			if(!mysql_query($query, $db)) {
				throw new Exception("Query failed ".mysql_error());
			}
		} catch (Exception $e) {
			if(mysql_errno() == 1065) {
				continue;
			} else {
				throw $e;
			}
		}
	}
	mysql_close($db);
	$dbphp = explode("\n", file_get_contents('./db.php'));
	$dbphp[18] = "'user' => '$username',";
	$dbphp[19] = "'pass' => '$password',";
	$dbphp[20] = "'base' => '$database',";
	$dbphp[25] = "'user' => '$username',";
	$dbphp[26] = "'pass' => '$password',";
	$dbphp[27] = "'base' => '$database'";
	file_put_contents('./db.php', implode("\n", $dbphp));
} catch (Exception $e) {
	die("\n".$e->getMessage()."\n");
}
$yn = readline("\nWould you like to configure Gammu to enable the SMS interface? (Y/N)");
$phones = array();
switch ($yn) {
	case 'y':
	case 'Y':
		$another = true;
		while ($another == true) {
			$phones[] = readline("\nEnter the path to the USB Serial device: ");
			$yn = readline("\nAdd another phone or dongle? (Y/N)");
			switch ($yn) {
				case 'n':
				case 'N':
					$another = false;
					break;
			}
		}
		break;
}
foreach ($phones as $id => $device) {
	$contents = array(
		'[gammu]',
		'port = '.$device,
		'Connection = at19200',
		'',
		'[smsd]',
		'PhoneID = phone'.$id,
		'CommTimeout = 5',
		'DeliveryReport = sms',
		'',
		'service = mysql',
		'user = '.$username,
		'password = '.$password,
		'pc = localhost',
		'database = '.$database.
		'',
		'LogFormat = textall',
		'logfile = stdout',
		'debuglevel = 1'
	);
	file_put_contents($_SERVER['HOME'].'/phone'.$id.'.gammu', implode("\n", $contents));
}
echo "\nInstall complete. Run the following commands to start the daemons:\n\n";
foreach ($phones as $id => $phone) {
	echo "sudo ./run_svc.sh gammu-smsd -c ".$_SERVER['HOME']."/phone$id.gammu -U gammu\n";
}
echo "sudo ./run_svc.sh php -q ".dirname(__FILE__)."/daemon.php";
echo "\n";