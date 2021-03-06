<?php
/*******************************************************
 * CampFireManager
 * Establish initial database connections and define
 * where the gammu_smsd database is stored for other
 * files to load
 * Version 0.5 2010-03-19 JonTheNiceGuy
 *******************************************************
 * Version History
 * 0.5 2010-03-19 Migrated from personal SVN store
 * at http://spriggs.org.uk/jon_code/CampFireManager
 * where all prior versions are stored to Google Code at
 * http://code.google.com/p/campfiremanager/
 ******************************************************/

//Database Connection Values
$db_CampFire=array(
  "host"=>"localhost", 
  "user"=>"CampFireManager", 
  "pass"=>"CampFireManager", 
  "base"=>"CampFireManager",
  "prefix"=>""
);
$db_Phone=array(
  "host"=>"localhost", 
  "user"=>"gammu", 
  "pass"=>"gammu", 
  "base"=>"gammu"
);

$base_dir=dirname(__FILE__) . "/libraries/";

require_once("{$base_dir}Camp_DB.php");
require_once("{$base_dir}SmsSource.php");
require_once("{$base_dir}OmbSource.php");
require_once("{$base_dir}CampUtils.php");

if(!isset($debug)) {$debug=0;}

//Initialize Class
if(!isset($__campfire) or !is_array($__campfire)) {$__campfire=array();}
$Camp_DB=new Camp_DB($db_CampFire['host'], $db_CampFire['user'], $db_CampFire['pass'], $db_CampFire['base'], $db_CampFire['prefix'], $__campfire, $debug);
