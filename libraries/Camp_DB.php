<?php
/*******************************************************
 * CampFireManager
 * Primary location for functional code.
 * Version 0.5 2010-03-19 JonTheNiceGuy
 *******************************************************
 * Version History
 * 0.5 2010-03-19 Migrated from personal SVN store
 * at http://spriggs.org.uk/jon_code/CampFireManager
 * where all prior versions are stored to Google Code at
 * http://code.google.com/p/campfiremanager/
 ******************************************************/

require_once($base_dir . "GenericBaseClass.php");
require_once($base_dir . "CampUtils.php");

class Camp_DB extends GenericBaseClass {
  // Cached data
  protected $intPersonID;
  protected $strName;
  protected $isAdmin;

  protected $strHostname;
  protected $intScreenID;

  protected $today;

  public $arrTalks = array();
  public $arrTalkSlots = array();
  public $arrTimeEndPoints = array();

  public $contact_fields = array('mailto', 'twitter', 'linkedin', 'identica', 'statusnet', 'facebook', 'irc', 'http', 'https');

  public $times = array();
  public $timetypes = array();
  public $rooms = array();
  public $config = array();

  // Calculated Data
  public $now_time;
  public $next_time;

  function __construct($db_host, $db_user, $db_pass, $db_base, $db_prefix, $arrAuthDetails=array(), $debug=0) {
    parent::__construct($db_host, $db_user, $db_pass, $db_base, $db_prefix, $debug);
    $this->refresh();
    if(count($arrAuthDetails)==1) {
      $this->getMe($arrAuthDetails);
    }
  }

  function collectWholeEventData() {
    $this->today='';
    $this->refresh();
  }

  function refresh() {
    $this->doDebug("refresh()");
    if(!isset($_SESSION['today'])) {
      $this->today = date("Y-m-d");
    } else {
      $this->today = date("Y-m-d", strtotime($_SESSION['today']));
    }
    $this->timetypes = $this->getTimeTypes();
    $this->times = $this->getTimes();
    $this->rooms = $this->getRooms();
    $this->config = $this->getConfig();
    if(''==CampUtils::arrayGet($this->config, 'event_start', '')) {
      $this->config['event_start'] = $this->today;
    }
    if(''==CampUtils::arrayGet($this->config, 'event_end', '')) {
      $this->config['event_end'] = $this->today;
    }
    $now_and_next = $this->getNowAndNextTime();
    $this->now_time = $now_and_next['now'];
    $this->next_time = $now_and_next['next'];
    list($this->arrTalkSlots, $this->arrTalks) = $this->readTalkData();
    if(isset($this->intPersonID)) {
      $this->getMe();
    }
  }

  function getNowAndNextTime($offset='') {
    $this->doDebug("getNowAndNextTime('$offset');");
    $this->makeTimeArray();
    $now_time = ($offset==='') ? strtotime('Now') : strtotime($offset);
    if(date('Y-m-d') == date('Y-m-d', strtotime($this->today))) {
      // Find the "Now" and "Next" time blocks
      $now = 0;
      $next = '';
      foreach($this->times as $intTimeID => $arrTime) {
        $timestring = $arrTime['strTime'];
        $timetype = $arrTime['intTimeType'];
        $intTime = strtotime(date("Y-m-d ") . $this->arrTimeEndPoints[$intTimeID]['s']);
        if ($intTime < $now_time) {
          $now = $intTimeID;
        }
        if ($intTime >= $now_time) {
          $next = $intTimeID;
          break;
        }
      }
    } elseif(strtotime(date('Y-m-d 00:00:00')) < strtotime(date('Y-m-d 00:00:00', strtotime($this->today)))) {
      $now=0;
      $next=0;
    } elseif(strtotime(date('Y-m-d 00:00:00')) > strtotime(date('Y-m-d 00:00:00', strtotime($this->today)))) {
      $now=99;
      $next=99;
    }
    return array('now'=>$now, 'next'=>$next);
  }

  function makeTimeArray() {
    if(count($this->arrTimeEndPoints)==0) {
      foreach($this->times as $intTimeID => $arrTime) {
        $strTime = $arrTime['strTime'];
        $intTimeType = $arrTime['intTimeType'];
        $timearray=explode('-', $strTime);
        $this->arrTimeEndPoints[$intTimeID]['s']=$timearray[0];
        $this->arrTimeEndPoints[$intTimeID]['e']=$timearray[1];
      }
    }
  }

  function doApiAuth($user, $pass) {
    $this->doDebug("doApiAuth($user, $pass)");
    $where="strAgent='" . $this->escape($user) . "'";
    $agents=$this->qryMap('strAgent', 'isEnabled', "{$this->prefix}agent WHERE $where");
    if(is_array($agents) and count($agents)>0) {
      foreach($agents as $agent) {
        if($agent['isEnabled']=='0') {
          return FALSE;
        }
      }
    } else {
      // Create a new agent entry
    }
    return $this->getMe(array('strAuthString'=>$pass));
  }

  function getMe($me=array(), $strSourceID='') {
    $set = '';
    $this->doDebug("getMe(" . print_r($me, TRUE) . ", $strSourceID);");

    // Do we already have a personID?
    if(isset($this->intPersonID)) {$where="intPersonID='{$this->intPersonID}'";}

    // Have we changed our personID?
    if(isset($me['intPersonID'])) {$where="intPersonID='{$me['intPersonID']}'";}

    // Or are we providing support to a user?
    if(isset($me['strAuthString'])) {$where="strAuthString='{$me['strAuthString']}'";}

    // Check for a Phone Account
    if(isset($me['number'])) {
      $me['number']=$this->escape($me['number']);
      if(isset($me['phone'])) {$me['phone']=$this->escape($me['phone']);} else {$me['phone']='';}
      $me['phone_nick']="Someone with a mobile number ending " . substr($me['number'], -4);
      $where="strPhoneNumber='{$me['number']}'";
      if(isset($me['text']) and strtoupper(substr($me['text'], 0, 2))=="O ") {
        $commands=explode(" ", $msg['text']);
        $where.=" OR strAuthString='{$commands[1]}'";
      }
    } else {$me['number']='';}

    // Check for a Microblog Account
    if(isset($me['microblog_account'])) {
      $me['microblog_account']=$this->escape($me['microblog_account']);
      if($me['microblog_name']!='') {
        $me['microblog_name']=$this->escape($me['microblog_name']);
      } else {
        $me['microblog_name']="A MicroBlogging user at " . $this->escape(parse_url($me['microblog_account'], PHP_URL_HOST));
      }
      $where="strMicroBlog='{$me['microblog_account']}'";
      if(strtoupper(substr($me['text'], 0, 2))=="O ") {
        $commands=explode(" ", $msg['text']);
        $where.=" OR strAuthString='{$commands[1]}'";
      }
    }

    // Check for an OpenID Account
    if(isset($me['OpenID'])) {
      $me['OpenID']=$this->escape($me['OpenID']);
      if($me['OpenID_Name']!='') {
        $me['OpenID_Name']=$this->escape($me['OpenID_Name']);
      } else {
        $me['OpenID_Name']="An OpenID User";
      }
      if(isset($me['OpenID_Mail'])) {
        $me['OpenID_Mail']="mailto:" . $this->escape($me['OpenID_Mail']);
      } else {
        $me['OpenID_Mail']='';
      }
      $where="strOpenID='{$me['OpenID']}'";
    }

    // Only look in the dataabase if we've actually been passed some form of Authentication
    if(isset($where)) {
      $people=$this->qryMap('intPersonID', 'strName', "{$this->prefix}people WHERE $where");
      if(count($people)==1) {
        // User exists
        foreach($people as $intPersonID=>$strName) {}
        // Specify where we got the last message from incase we want to reply
        $set='';
        if(isset($me['phone'])) {$set.="strDefaultReply='{$me['phone']}'";}
        if(isset($me['phone_number'])) {if($set!='') {$set.=", ";} $set.="strPhoneNumber='{$me['phone_number']}'"; }
        if(isset($me['microblog_account'])) {if($set!='') {$set.=", ";} $set.="strMicroBlog='{$me['microblog_account']}'"; }
        if(isset($me['microblog_name']) AND $strName=='') {if($set!='') {$set.=", ";} $set.="strName='{$me['microblog_name']}'"; }
        if($set!='') {$this->boolUpdateOrInsertSql("UPDATE {$this->prefix}people SET $set WHERE intPersonID='$intPersonID'");}
      } else {
        // User doesn't exist
        // Generate an authString for them
        $authString='';
        while($authString=='') {
          $authString=genRandStr(5, 9);
          if(count($this->qryMap('intPersonID', 'strAuthString', "{$this->prefix}people WHERE strAuthString='$authString'"))!=0) {
            $authString='';
          }
        }
        // Now add it to the database
        if ( CampUtils::arrayGet($me, 'number', false) ) {
          $this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}people (strPhoneNumber, strName, strDefaultReply, strAuthString) VALUES ('{$me['number']}', '{$me['phone_nick']}', '{$me['phone']}', '$authString')");
        } elseif( CampUtils::arrayGet($me, 'microblog_account', false) ) {
          $this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}people (strMicroBlog, strName, strAuthString, strDefaultReply) VALUES ('{$me['microblog_account']}', '{$me['microblog_name']}', '$authString', '{$me['strDefaultReply']}')");
        } elseif( CampUtils::arrayGet($me, 'OpenID', false) ) {
          $this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}people (strOpenID, strName, strContactInfo, strAuthString) VALUES ('{$me['OpenID']}', '{$me['OpenID_Name']}', '{$me['OpenID_Mail']}', '$authString')");
        } else {
          $this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}people (strName, strAuthString) VALUES ('Someone being supported by one of the crew', '$authString')");
        }
        // And return the user IDs
        $people=$this->qryMap('intPersonID', 'strName', "{$this->prefix}people WHERE strAuthString='$authString'");
        foreach($people as $intPersonID=>$strName) {}
        $this->intPersonID=$intPersonID;
        $event_title = CampUtils::arrayGet($this->config, 'event_title', 'CampFireDefaultEvent');
        $this->sendMessage("Welcome to $event_title. Your authorization string for this system is: $authString.");
      }
      $checkIsAdmin=$this->qryMap('intPersonID', 'boolIsAdmin', "{$this->prefix}people WHERE intPersonID='$intPersonID'");
      $checkIsSupport=$this->qryMap('intPersonID', 'boolIsSupport', "{$this->prefix}people WHERE intPersonID='$intPersonID'");
      $this->intPersonID=$intPersonID;
      $this->_addChange(array('intPersonID'=>$this->intPersonID));
      $this->strName=$strName;
      $this->isAdmin=$checkIsAdmin[$intPersonID];
      $this->isSupport=$checkIsSupport[$intPersonID];
      return TRUE;
    } else {
      return FALSE;
    }
  }

  function sendMessage($strMessage) {
    global $sources;

    $this->doDebug("sendMessage($strMessage) (using intPersonID of {$this->intPersonID})");
    $me=$this->allMyDetails();
    $this->doDebug(print_r(array('me'=>$me, 'sources'=>$sources), TRUE), 2);
    if($me['strDefaultReply']!='' AND isset($sources[$me['strDefaultReply']])) {
      $sources[$me['strDefaultReply']]->sendMessages($strMessage);
    } elseif($me['strDefaultReply']!='') {
      $sources['Phone']->sendMessages($strMessage, $me['strDefaultReply'], $me['strPhoneNumber']);
    }
  }

  function getAuthStrings() {
    return $this->qryMap('intPersonID', 'strAuthString', "people WHERE intPersonID='{$this->intPersonID}' AND strAuthString!=''");
  }

  function fixTalk($intTalkID) {
    $this->doDebug("fixTalk($intTalkID);");
    if($this->arrTalks[$intTalkID]['boolFixed']==0) {
      $this->doDebug("Talk $intTalkID is fixed", 2);
      $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}talks SET boolFixed=1 WHERE intTalkID='$intTalkID'");
    } else {
      $this->doDebug("Talk $intTalkID is unfixed", 2);
      $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}talks SET boolFixed=0 WHERE intTalkID='$intTalkID'");
    }
    $this->refresh();
  }

  protected function _createTalk($intTimeID, $intRoomID, $intPersonID, $strTalkTitle, $boolFixed, $intLength, $date='') {
    if($date=='') {$date=$this->today;}
    $this->doDebug("_createTalk('$intTimeID', '$intRoomID', '$intPersonID', '$strTalkTitle', '$boolFixed', '$intLength', '$date');");
    if($this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}talks (intTimeID, datTalk, intRoomID, intPersonID, strTalkTitle, boolFixed, intLength) VALUES ('$intTimeID', '$date', '$intRoomID', '$intPersonID', '$strTalkTitle', '$boolFixed', '$intLength')")) {
      $intTalkID=$this->getInsertID();
      $this->_addChange(array('intTalkID'=>$intTalkID));
      return($intTalkID);
    } else {
      return false;
    }
  }

  protected function _editTalk($intTalkID, $strTalkTitle) {
    $this->doDebug("_editTalk('$intTalkID', '$strTalkTitle');");
    if($strTalkTitle!=$this->arrTalks[$intTalkID]['strTalkTitle']) {
      $this->_addChange(array('intTalkID'=>$intTalkID));
      $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}talks SET strTalkTitle='$strTalkTitle' WHERE intTalkID='$intTalkID'");
    }
  }

  protected function _deleteTalk($intTalkID) {
    $this->doDebug("_deleteTalk('$intTalkID');");
    $this->_addChange(array('intTalkID'=>$intTalkID));
    $this->boolUpdateOrInsertSql("DELETE FROM {$this->prefix}talks WHERE intTalkID='$intTalkID'");
    $this->boolUpdateOrInsertSql("DELETE FROM {$this->prefix}attendees WHERE intTalkID='$intTalkID'");
  }

  protected function _attendTalk($intTalkID) {
    $this->doDebug("_attendTalk('$intTalkID');");
    $isSet=$this->qryMap("intTalkID", "intPersonID", "{$this->prefix}attendees WHERE intTalkID='$intTalkID' and intPersonID='{$this->intPersonID}'");
    if( ! (is_array($isSet) and count($isSet)>0)) {
      $this->boolUpdateOrInsertSql("REPLACE INTO {$this->prefix}attendees (intTalkID, intPersonID) VALUES ('$intTalkID', '{$this->intPersonID}')");
      $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}talks SET intAttendees = intAttendees + 1 WHERE intTalkID='$intTalkID'");
      $this->_addChange(array('intTalkID'=>$intTalkID));
    }
  }

  protected function _declineTalk($intTalkID) {
    $this->doDebug("_declineTalk('$intTalkID');");
    if($this->boolUpdateOrInsertSql("DELETE FROM {$this->prefix}attendees WHERE intTalkID='$intTalkID' AND intPersonID='{$this->intPersonID}'")) {
      $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}talks SET intAttendees = intAttendees - 1 WHERE intTalkID='$intTalkID'");
      $this->_addChange(array('intTalkID'=>$intTalkID));
    }
  }

  function setConfig($key, $value) {
    $this->doDebug("setConfig($key, $value);");
    $val = CampUtils::arrayGet($this->config, $key, '');
    if($val != $value) {
      $key = $this->escape($key);
      $value = $this->escape($value);
      if($val=='' AND $value!='') {$this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}config (strConfig, strValue) VALUES ('$key', '$value')");}
      if($val!='' AND $value!='') {$this->boolUpdateOrInsertSql("UPDATE config SET strValue='$value' WHERE strConfig='$key'");}
      if($val!='' AND $value=='') {$this->boolUpdateOrInsertSql("DELETE FROM config WHERE strConfig='$key'");}
    }
  }

  function updateTime($intTimeID, $strTime, $intTimeType) {
    $this->doDebug("updateTime($intTimeID, $strTime, $intTimeType);");
    if($intTimeID=='' AND $strTime!='') {
      $this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}times (strTime, intTimeType) VALUES ('$strTime', '$intTimeType')");
    } elseif(is_array($this->times) and isset($this->times[$intTimeID]) and $this->times[$intTimeID]['strTime']!=$strTime AND $strTime!='') {
      $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}times SET strTime='$strTime', intTimeType='$intTimeType' WHERE intTimeID='$intTimeID'");
    } elseif(is_array($this->times) and isset($this->times[$intTimeID]) and $this->times[$intTimeID]['strTime']!='' AND $strTime=='') {
      $this->boolUpdateOrInsertSql("TRUNCATE {$this->prefix}times");
      foreach($this->times as $old_intTimeID=>$arrTime) {
        $strTime=$arrTime['strTime'];
        if($old_intTimeID!=$intTimeID) {$this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}times (strTime, intTimeType) VALUES ('$strTime', '$intTimeType')");}
      }
    }
  }

  function updateTimeType($intTimeTypeID, $strTimeType) {
    $this->doDebug("updateTimeType($intTimeTypeID, $strTimeType);");
    if($intTimeTypeID=='' AND $strTimeType!='') {
      $this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}timetypes (strTimeType) VALUES ('$strTimeType')");
    } elseif(is_array($this->timetypes) and isset($this->timetypes[$intTimeTypeID]) and $this->timetypes[$intTimeTypeID]!=$strTimeType AND $strTimeType!='') {
      $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}timetypes SET strTimeType='$strTimeType' WHERE intTimeTypeID='$intTimeTypeID'");
    } elseif(is_array($this->timetypes) and isset($this->timetypes[$intTimeTypeID]) and $this->timetypes[$intTimeTypeID]!='' AND $strTimeType=='') {
      $this->boolUpdateOrInsertSql("TRUNCATE {$this->prefix}timetypes");
      foreach($this->timetypes as $old_intTimeTypeID=>$old_strTimeType) {
        if($old_intTimeTypeID!=$intTimeTypeID and $old_strTimeType) {$this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}timetypes (strTimeType) VALUES ('$strTimeType')");}
      }
    }
  }

  function updateRoom($intRoomID, $strRoom, $intCapacity, $boolIsDynamic) {
    $this->setDebug(255);
    $this->doDebug("updateRoom($intRoomID, $strRoom, $intCapacity, $boolIsDynamic) from " . print_r($this->rooms[$intRoomID], TRUE) . ";");
    if(!isset($this->rooms[$intRoomID]) AND $strRoom!='' AND $intCapacity!='') {
      $this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}rooms (strRoom, intCapacity, boolIsDynamic) VALUES ('$strRoom', '$intCapacity', '$boolIsDynamic')");
    }
    if(($this->rooms[$intRoomID]['strRoom']!=$strRoom AND $strRoom!='') or ($this->rooms[$intRoomID]['intCapacity']!=$intCapacity AND $intCapacity!='') or ($this->rooms[$intRoomID]['boolIsDynamic']!=$boolIsDynamic)) {
      $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}rooms SET strRoom='$strRoom', intCapacity='$intCapacity', boolIsDynamic='$boolIsDynamic' WHERE intRoomID='$intRoomID'");
    }
    if(($this->rooms[$intRoomID]['strRoom']!=$strRoom AND $strRoom=='') OR ($this->rooms[$intRoomID]['intRoomID']!=$intCapacity AND $intCapacity=='')) {
      $this->boolUpdateOrInsertSql("TRUNCATE {$this->prefix}rooms");
      foreach($this->rooms as $old_intRoomID=>$arrRoom) {
        if($old_intRoomID!=$intRoomID) {$this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}rooms (strRoom, intCapacity, boolIsDynamic) VALUES ('" . $arrRoom['strRoom'] . "', '" . $arrRoom['intCapacity'] . "', '" . $arrRoom['boolIsDynamic'] . "')");}
      }
    }
    $this->setDebug(0);
  }

  function updateMb($intMbID, $strApi, $strUser, $strPass) {
    $arrMbs=$this->getMicroBloggingAccounts();
    $this->doDebug("updateMb($intMbID, $strApi, $strUser, $strPass);");
    if(!isset($arrMbs[$intMbID]) AND $strApi!='' AND $strUser!='' AND $strPass!='') {
      $this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}account_microblog (strApiBase, strAccount, strPassword) VALUES ('$strApi', '$strUser', '$strPass')");
    }
    if(($arrMbs[$intMbID]['strApiBase']!=$strApi AND $strApi!='') OR ($arrMbs[$intMbID]['strAccount']!=$strUser AND $strUser!='') OR ($arrMbs[$intMbID]['strPassword']!=$strPass AND $strPass!='')) {
      $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}account_microblog SET strApiBase='$strApi', strAccount='$strUser', strPassword='$strPass' WHERE intMbID='$intMbID'");
    }
    if(($arrMbs[$intMbID]['strApiBase']!=$strApi AND $strApi=='') OR ($arrMbs[$intMbID]['strAccount']!=$strUser AND $strUser=='') OR ($arrMbs[$intMbID]['strPassword']!=$strPass AND $strPass=='')) {
      $this->boolUpdateOrInsertSql("TRUNCATE {$this->prefix}account_microblog");
      foreach($arrMbs as $old_intMbID=>$arrMb) {
        if($old_intMbID!=$intMbID) {$this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}account_microblog (strApiBase, strAccount, strPassword) VALUES ('{$arrMb['strApiBase']}', '{$arrMb['strAccount']}', '{$arrMb['strPassword']}')");}
      }
    }
  }

  function updatePhone($intPhoneID, $strPhoneNumber, $strPhoneNetwork, $strPhoneGammu) {
    $arrPhones=$this->getPhones();
    $this->doDebug("updatePhone($intPhoneID, $strPhoneNumber, $strPhoneNetwork, $strPhoneGammu);");
    $intPhoneID=$this->escape(stripslashes($intPhoneID));
    $strPhoneNumber=$this->escape(stripslashes($strPhoneNumber));
    $strPhoneNetwork=$this->escape(stripslashes($strPhoneNetwork));
    $strPhoneGammu=$this->escape(stripslashes($strPhoneGammu));
    if(!isset($arrPhones[$intPhoneID]) AND $strPhoneNumber!='' AND $strPhoneNetwork!='' AND $strPhoneGammu!='') {
      $this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}account_phones (strNumber, strPhone, strGammuRef) VALUES ('$strPhoneNumber', '$strPhoneNetwork', '$strPhoneGammu')");
    }
    if(($arrPhones[$intPhoneID]['strNumber']!=$strPhoneNumber AND $strPhoneNumber!='') OR ($arrPhones[$intPhoneID]['strPhone']!=$strPhoneNetwork AND $strPhoneNetwork!='') OR ($arrPhones[$intPhoneID]['strGammuRef']!=$strPhoneGammu AND $strPhoneGammu!='')) {
      $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}account_phones SET strNumber='$strPhoneNumber', strPhone='$strPhoneNetwork', strGammuRef='$strPhoneGammu' WHERE intPhoneID='$intPhoneID'");
    }
    if(($arrPhones[$intPhoneID]['strNumber']!=$strPhoneNumber AND $strPhoneNumber=='') OR ($arrPhones[$intPhoneID]['strPhone']!=$strPhoneNetwork AND $strPhoneNetwork=='') OR ($arrPhones[$intPhoneID]['strGammuRef']!=$strPhoneGammu AND $strPhoneGammu=='')) {
      $this->boolUpdateOrInsertSql("TRUNCATE {$this->prefix}account_phones");
      foreach($arrPhones as $old_intPhoneID=>$arrPhone) {
        if($old_intPhoneID!=$intPhoneID) {$this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}account_phones (strNumber, strPhone, strGammuRef) VALUES ('{$arrPhone['strNumber']}', '{$arrPhone['strPhone']}', '{$arrPhone['strGammuRef']}')");}
      }
    }
  }

  function getConfig() {
    $this->doDebug("getConfig();");
    return $this->qryMap('strConfig', 'strValue', "{$this->prefix}config");
  }

  function generateNewAdminKey() {
    $this->doDebug("generateNewAdminKey();");
    if(1==CampUtils::arrayGet($this->config, 'admin_regen', 1)) {
      $this->boolUpdateOrInsertSql("REPLACE INTO {$this->prefix}config (strConfig, strValue) VALUES ('adminkey', '" . genRandStr(10, 10) . "')");
      $this->config=$this->getConfig();
    }
  }

  function generateNewSupportKey() {
    $this->doDebug("generateNewSupportKey();");
    if(1==CampUtils::arrayGet($this->config, 'support_regen', 1)) {
      $this->boolUpdateOrInsertSql("REPLACE INTO {$this->prefix}config (strConfig, strValue) VALUES ('supportkey', '" . genRandStr(10, 10) . "')");
      $this->config=$this->getConfig();
    }
  }

  function updatePhoneData($strPhoneID, $intSignal) {
    $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}account_phones SET intSignal='$intSignal' WHERE strGammuRef='$strPhoneID'");
  }

  function getAllConnectionMethods() {
    $this->doDebug("getAllConnectionMethods();");
    $phones=$this->getPhones();
    $omb=$this->getMicroBloggingAccounts();
    $config=$this->config;

    $phone_numbers='';
    foreach($phones as $phone) {
      if($phone_numbers!='') {$phone_numbers.=", ";}
      $phone_numbers.="{$phone['strNumber']} on the {$phone['strPhone']} Network (with {$phone['intSignal']}% signal)";
    }

    $omb_accounts='';
    foreach($omb as $omb_ac) {
      if($omb_accounts!='') {$omb_accounts.=", ";}
      $omb_server=
      $omb_accounts.="@{$omb_ac['strAccount']} on " . parse_url($omb_ac['strApiBase'], PHP_URL_HOST);
    }

    $website = CampUtils::arrayGet($this->config, 'website', '');
    return array('tel'=>$phone_numbers, 'omb'=>$omb_accounts, 'web'=>$website);
  }

  function getMicroBloggingAccounts() {
    $this->doDebug("getMicroBloggingAccounts();");
    return $this->qryArray("SELECT * FROM {$this->prefix}account_microblog", 'intMbID');
  }

  function getLastMbUpdate($intMbID) {
    $this->doDebug("getLastMbUpdate($intMbID);");
    $return = $this->qryMap('intMbID', 'intLastMessage', "{$this->prefix}account_microblog WHERE intMbID='$intMbID'");
    $this->doDebug("Returns: " . $return[$intMbID] . "");
    return $return[$intMbID];
  }

  function setLastMbUpdate($intMbID, $intLastMessage) {
    $this->doDebug("setLastMbUpdate($intMbID, $intLastMessage);");
    $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}account_microblog SET intLastMessage='$intLastMessage' WHERE intMbID='$intMbID'");
  }

  function getPhones() {
    $this->doDebug("getPhones();");
    return $this->qryArray("SELECT * FROM {$this->prefix}account_phones", 'intPhoneID');
  }

  function getPeople() {
    $this->doDebug("getPeople();");
    return $this->qryArray("SELECT * FROM {$this->prefix}people", 'intPersonID');
  }

  function getPerson($s=array()) {
    $this->doDebug("getPerson(" . print_r($s, TRUE) . ");");
    if(count($s)==0) {return FALSE;}
    $w='';
    foreach($s as $key=>$value) {
      if($w!='') {$w.=" AND ";}
      if(strpos($value, '%')!==FALSE) {
        $w.="$key LIKE '" . $this->escape($value) . "'";
      } else {
        $w.="$key='" . $this->escape($value) . "'";
      }
    }
    return $this->qryArray("SELECT * FROM {$this->prefix}people WHERE $w", 'intPersonID');
  }

  function getRooms() {
    $this->doDebug("getRooms();");
    return $this->qryArray("SELECT * FROM {$this->prefix}rooms", 'intRoomID');
  }

  function getTimeTypes() {
    $this->doDebug("getTimeTypes();");
    return $this->qryMap('intTimeTypeID', 'strTimeType', "{$this->prefix}timetypes");
  }

  function getTimes() {
    $this->doDebug("getTimes();");
    return $this->qryArray("SELECT * FROM {$this->prefix}times", 'intTimeID');
  }

  function getTalks() {
    $this->doDebug("getTalks();");
    if($this->today!='') {$w="WHERE datTalk='{$this->today}'";} else {$w='';}
    $whole_result=$this->qryArray("SELECT * FROM {$this->prefix}talks $w", "intTalkID");
    $return=array();
    foreach($whole_result as $row_id=>$row_data) {
      foreach($row_data as $column_name=>$column_data) {
        switch($column_name) {
          case "intTalkID":
          case "intTimeID":
          case "datTalk":
          case "intRoomID":
          case "intPersonID":
          case "strTalkTitle":
          case "boolFixed":
          case "intAttendees":
          case "intLength":
            $return[$row_id][$column_name]=$column_data;
        }
      }
    }
    return $return;
  }

  function getAttendeesCount() {
    $this->doDebug("getAttendeesCount();");
    return $this->qryMap('intTalkID', 'count(intPersonID)', "{$this->prefix}attendees");
  }

  function getAttendees() {
    $this->doDebug("getAttendees();");
    return $this->qryArray("SELECT * from {$this->prefix}attendees", 'intAttendID');
  }

  function getTalksIAmAttending() {
    $this->doDebug("getTalksIAmAttending();");
    return $this->qryMap('intTalkID', 'intAttendID', "{$this->prefix}attendees WHERE intPersonID='{$this->intPersonID}'");
  }

  function getMyTalks() {
    $this->doDebug("getMyTalks();");
    $return=array();
    foreach($this->arrTalks as $intTalkID=>$arrTalk) {
      if($arrTalk['intPersonID']==$this->intPersonID) {
        $return[$intTalkID]=TRUE;
      }
    }
    return $return;
  }

  function getPresenters() {
    $this->doDebug("getPresenters();");
    return $this->qryArray("SELECT {$this->prefix}people.intPersonID, {$this->prefix}people.strName, {$this->prefix}people.strContactInfo FROM {$this->prefix}people, {$this->prefix}talks WHERE {$this->prefix}talks.intPersonID={$this->prefix}people.intPersonID", 'intPersonID');
  }

  function getScreens() {
    $this->doDebug("getScreens();");
    if(!isset($this->strHostname)) {$this->strHostname=$this->escape(trim($_SERVER['REMOTE_ADDR']));}
    $screens=$this->qryMap('strHostname', 'intScreenID', "{$this->prefix}screens WHERE strHostname='{$this->strHostname}'");
    if(isset($screens[$this->strHostname])) {
      $intScreenID=$screens[$this->strHostname];
    } else {
      $this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}screens (strHostname) VALUES ('{$this->strHostname}')");
      $screens=$this->qryMap('strHostname', 'intScreenID', "{$this->prefix}screens WHERE strHostname='{$this->strHostname}'");
      $intScreenID=$screens[$this->strHostname];
    }
    $this->intScreenID=$intScreenID;
  }

  function setDirections($room_number, $room_direction) {;
    $this->getScreens();
    $room_number=$this->escape($room_number);
    $room_direction=$this->escape($room_direction);

    $room_directions=$this->qryMap('intDestRoomID', 'intDirectionURDL', "{$this->prefix}room_directions WHERE intScreenID='{$this->intScreenID}'");
    if(!isset($room_directions[$room_number])) {
      $this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}room_directions (intScreenID, intDestRoomID, intDirectionURDL) VALUES ('{$this->intScreenID}', '$room_number', '$room_direction')");
    } else {
      $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}room_directions SET intDirectionURDL='$room_direction' WHERE intScreenID='{$this->intScreenID}' AND intDestRoomID='$room_direction'");
    }
    header("Location: " . $_SERVER['PHP_SELF']);
  }

  function getDirections() {
    $this->doDebug("getDirections();");
    $this->getScreens();
    return $this->qryMap('intDestRoomID', 'intDirectionURDL', "{$this->prefix}room_directions WHERE intScreenID='{$this->intScreenID}'");
  }

  function editTalk($commands) {
    $this->doDebug("editTalk(" . print_r($commands, TRUE) . ");");
    $talk='';
    $stop=FALSE;
    foreach($commands as $cid=>$command) {
      switch($cid) {
        case 0:
          if($command+0>0) {$talkid=$command;} else {$stop=TRUE;}
          break;
        case 1:
          if($command+0>0) {$time=$command;} else {$stop=TRUE;}
          break;
        default:
          if($talk!='') {$talk.=" ";}
          $talk.=$command;
      }
    }
    if($stop==TRUE) {
      $this->updateStatusScreen("{$this->strName} didn't include enough detail when trying to edit the title of their talk. (Received " . print_r($commands, TRUE) . ")");
      return FALSE;
    } else {
      list($talks, $talk_data)=$this->readTalkData();

      if(($talk_data[$talkid]['intPersonID']==$this->intPersonID OR $this->isAdmin) and $talk_data[$talkid]['intTimeID']==$time and $time>=$this->next_time) {
        $this->_editTalk($talkid, $talk);
        $this->updateStatusScreen("{$this->strName} renamed the talk ($talkid) to '$talk'");
        return TRUE;
      } else {
        $this->updateStatusScreen("{$this->strName} tried to retitle a talk ($talkid) they didn't propose, or that was not at the time the talk is currently in. (Received " . print_r($commands, TRUE) . ")");
        return FALSE;
      }
    }
  }

  protected function _setRoom($intRoomID, $intTalkID) {
    $this->doDebug("_setRoom('$intRoomID', '$intTalkID');");
    if($this->arrTalks[$intTalkID]['intRoomID']!=$intRoomID) {
      $this->_addChange(array('intTalkID'=>$intTalkID));
      $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}talks SET intRoomID='$intRoomID' WHERE intTalkID='$intTalkID'");
    }
  }
  function getChanges($reset=TRUE) {
    $this->doDebug("getChanges($reset)");
    $tdata=$this->qryMap("intChangeID", "intTalkID", "{$this->prefix}changes WHERE intTalkID!='0'");
    $pdata=$this->qryMap("intChangeID", "intPersonID", "{$this->prefix}changes WHERE intPersonID!='0'");
    if($reset==TRUE) {
      if(is_array($tdata) and count($tdata)>0) {
        foreach($tdata as $key=>$value) {
          $this->boolUpdateOrInsertSql("DELETE FROM {$this->prefix}changes WHERE intChangeID='$key'");
        }
      }
      if(is_array(ptdata) and count($pdata)>0) {
        foreach($pdata as $key=>$value) {
          $this->boolUpdateOrInsertSql("DELETE FROM {$this->prefix}changes WHERE intChangeID='$key'");
        }
      }
    }
    return(array('talks'=>$tdata, 'people'=>$pdata));
  }


  function _addChange($arrChange=array()) {
    $this->doDebug("_addChange(" . print_r($arrChange, TRUE) . ");");
    if(is_array($arrChange) and count($arrChange)>0) {
      foreach($arrChange as $key=>$value) {
        switch($key) {
          case 'intTalkID':
          case 'intPersonID':
            $data=$this->qryMap($key, $value, "{$this->prefix}changes where $key='$value'");
            if(!is_array($data) or count($data)==0) {
              $this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}changes ($key) VALUES ('$value')");
            }
            break;
          default:
        }
      }
    }
  }

  function createReply($message) {
    $this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}replies (strMessage, datInsert, intPersonID) VALUES ('". $this->escape($message) . "', NOW(), '{$this->intPersonID}')");
  }

  function updateStatusScreen($message, $intPersonID='') {
    $this->doDebug("updateStatusScreen('$message', '$intPersonID');");
    if($intPersonID=='') {$intPersonID=$this->intPersonID;}
    $this->boolUpdateOrInsertSql("INSERT INTO {$this->prefix}sms_screen (strMessage, datInsert, intPersonID) VALUES ('". $this->escape(stripslashes($message)) . "', NOW(), '$intPersonID')");
  }

  function showStatusScreen($number=50) {
    $where='';
    if($this->intPersonID!='') {$where="intPersonID='{$this->intPersonID}' AND ";}
    return $this->qryMap('intUpdateID', 'strMessage', "{$this->prefix}sms_screen WHERE $where datInsert>'" . date("Y-m-d H:i:s", strtotime("-15 minutes")) . "' ORDER BY datInsert DESC", '',  "LIMIT 0, $number");
  }

  function attendTalk($intTalkID) {
    $this->doDebug("attendTalk('$intTalkID');");
    if($this->arrTalks[$intTalkID]['intPersonID']!=$this->intPersonID) {
      $attendees=$this->getTalksIAmAttending();
      if(!isset($attendees[$intTalkID])) {
        $this->_attendTalk($intTalkID);
        $this->updateStatusScreen("{$this->strName} is attending the talk number $intTalkID.");
        $this->sortRooms();
        return true;
      }
    } else {
      $this->updateStatusScreen("{$this->strName} tried to attend their own talk $intTalkID.");
      return false;
    }
  }

  function declineTalk($intTalkID) {
    $this->doDebug("declineTalk('$intTalkID');");
    if($this->arrTalks[$intTalkID]['intPersonID']!=$this->intPersonID) {
      $attendees=$this->getTalksIAmAttending();
      if(isset($attendees[$intTalkID])) {
        $this->_declineTalk($intTalkID);
        $this->updateStatusScreen("{$this->strName} is no longer attending the talk number $intTalkID.");
        $this->sortRooms();
        return true;
      }
    }
    return false;
  }

  function insertStaticTalk($time, $room, $talk, $length=1, $date='') {
    if($this->isAdmin==0) {$date='';}
    if($date=='') {$date=date('Y-m-d', strtotime($this->today));} else {$date=date('Y-m-d', strtotime($date));}
    $this->doDebug("insertStaticTalk($time, $room, $talk, $length, $date);");
    $talk=$this->escape($talk);
    $stop=TRUE;
    if(isset($this->rooms[$room]) and (0==$this->rooms[$room]['boolIsDynamic'] or 1==CampUtils::arrayGet($this->config, 'dynamically_sort_whole_board_by_attendees', "0"))) {$stop=FALSE;}
    $intTalkID=0;
    list($this->arrTalkSlots, $this->arrTalks)=$this->readTalkData($date);
    if($date==date('Y-m-d') and $time<=$this->now_time) {$time=$this->now_time+1;}
    while($intTalkID==0 AND $stop==FALSE) {
      if(count($this->arrTalkSlots[$time])>0) {
        foreach($this->arrTalkSlots[$time] as $intRoomID=>$intTalkNumber) {
          if($intTalkNumber<=0 and $intRoomID==$room) { // You can't assign a talk in a pre-existing slot
            $roomfree=1;
            if($length>1 and ($time-1)+$length>count($this->times)) {
              for($i=$time+1; $i<=($time-1)+$length; $i++) {
                if($talks[$i][$intRoomID]>0) {$roomfree=0;} // A talk has already been assigned to this room in this slot.
              }
            }
            if($roomfree==1) {
              $intTalkID=$this->_createTalk($time, $room, $this->intPersonID, $talk, 1, $length, $date);
              $this->updateStatusScreen("{$this->strName} proposed a talk about '$talk' into the slot {$this->times[$time]['strTime']}. It is talk number $intTalkID.");
              $this->sortRooms();
              return $intTalkID;
            }
          }
        }
      }
      if($intTalkID==0) {
        $time++;
        if($this->times[$time]['intTimeType']!=0) {$time++;} // An automatic allocation shouldn't push your talk into lunch. You must only select it.
        if($time>count($this->times)) {$stop=TRUE;}
      }
    }
    if($stop!=FALSE) {
      $this->updateStatusScreen("{$this->strName} was unable to propose a talk because there were no more available slots. (Received " . print_r($commands, TRUE) . ")");
      return FALSE;
    }
  }

  function insertTalk($commands, $boolFixed=0, $date='') {
    if($this->isAdmin==0) {$date='';}
    if($date=='') {$date=date('Y-m-d', strtotime($this->today));} else {$date=date('Y-m-d', strtotime($date));}
    $this->doDebug("insertTalk(" . print_r($commands, TRUE) . ", $boolFixed, $date);");
    $talk='';
    $time=0;
    $length=1;
    $stop=FALSE;
    $intTalkID=0;
    foreach($commands as $cid=>$command) {
      switch($cid) {
        case 0:
          if($command+0>0) {$time=$command;} else {$stop=TRUE;}
          break;
        case 1:
          if(0==CampUtils::arrayGet($this->config, 'sessions_fixed_to_one_slot', 0)) {
            if($command+0>0) {$length=$command;} else {$stop=TRUE;}
            break;
          }
        default:
          if($talk!='') {$talk.=" ";}
          $talk.=$command;
      }
    }
    if($stop==TRUE) {
      $this->updateStatusScreen("{$this->strName} didn't provide enough information to Propose a talk. (Received " . print_r($commands, TRUE) . ")");
      return false;
    } else {
      if($boolFixed==1 and $this->isAdmin==0) {$boolFixed=0;}
      $talk=$this->escape($talk);
      list($this->arrTalkSlots, $this->arrTalks)=$this->readTalkData();
      if($date==date('Y-m-d') and $time<=$this->now_time) {$time=$this->now_time+1;}
      while($intTalkID==0 AND $stop==FALSE) {
        if(count($this->arrTalkSlots[$time])>0) {
          foreach($this->arrTalkSlots[$time] as $room=>$intTalkNumber) {
            if($intTalkNumber<=0 and $this->rooms[$room]['boolIsDynamic']==1) { // You can't assign a talk in a pre-existing slot
              $roomfree=1;
              if($length>1 and ($time-1)+$length>count($this->times)) {
                for($i=$time+1; $i<=($time-1)+$length; $i++) {
                  if($talks[$i][$room]>0) {$roomfree=0;} // A talk has already been assigned to this room in this slot.
                }
              }
              if($roomfree==1) {
                $intTalkID=$this->_createTalk($time, $room, $this->intPersonID, $talk, $boolFixed, $length, $date);
                if ($intTalkID != false) {
                    $this->updateStatusScreen("{$this->strName} proposed a talk about '$talk' into the slot {$this->times[$time]['strTime']}. It is talk number $intTalkID.");
                    $this->sortRooms();
                    return $intTalkID;
                } else {
                    return false;
                }
              }
            }
          }
        }
        if($intTalkID==0) {
          $time++;
          if($this->times[$time]['intTimeType']!=0) {$time++;} // An automatic allocation shouldn't push your talk into lunch. You must only select it.
          if($time>count($this->times)) {$stop=TRUE;}
        }
      }
      if($stop!=FALSE) {
        $this->updateStatusScreen("{$this->strName} was unable to propose a talk because there were no more available slots. (Received " . print_r($commands, TRUE) . ")");
        return false;
      }
    }
  }

  function cancelTalk($commands) {
    $this->doDebug("cancelTalk(" . print_r($commands, TRUE) . ");");
    $reason='';
    $stop=FALSE;
    foreach($commands as $cid=>$command) {
      switch($cid) {
        case 0:
          if($command+0>0) {$talkid=$command;} else {$stop=TRUE;}
          break;
        case 1:
          if($command+0>0) {$time=$command;} else {$stop=TRUE;}
          break;
        default:
          if($reason!='') {$reason.=" ";}
          $reason.=$command;
      }
    }
    if($stop==TRUE) {
      $this->updateStatusScreen("{$this->strName} didn't include enough detail when trying to cancel a talk (Received " . print_r($commands, TRUE) . ")");
      return FALSE;
    } else {
      list($talks, $talk_data)=$this->readTalkData();

      if(($talk_data[$talkid]['intPersonID']==$this->intPersonID OR $this->isAdmin) and $talk_data[$talkid]['intTimeID']==$time and $time>=$this->next_time) {
        $this->_deleteTalk($talkid);
        $strReason='';
        if($reason!='') {$strReason=" because: $reason";}
        $this->updateStatusScreen(trim("{$this->strName} cancelled their talk ($talkid) $strReason"));
        $this->sortRooms();
        return TRUE;
      } else {
        $this->updateStatusScreen("{$this->strName} tried to cancel a talk ($talkid) they didn't propose, or that was not at the time the talk is currently in.  (Received " . print_r($commands, TRUE) . ")");
        return FALSE;
      }
    }
  }

  function readTalkData($date='') {
    if($this->isAdmin==0) {$date='';}
    if($date=='') {$date=date('Y-m-d');} else {$date=date('Y-m-d', strtotime($date));}
    $this->doDebug("readTalkData($date);");
    $arrTalkSlots = array();
    $arrTalks=$this->getTalks();
    $arrAttendanceByTalks=$this->getAttendeesCount();
    $arrPeopleAsPresentersOnly=$this->getPresenters();

    // Prepopulate the talk table with "empty" and "lunch" slots
    foreach($this->times as $intTimeID => $arrTime) {
      $strTime = $arrTime['strTime'];
      $intTimeType = $arrTime['intTimeType'];
      foreach($this->rooms as $room=>$data_room) {
        $arrTalkSlots[$intTimeID][$room]=-$intTimeType;
      }
    }

    if(is_array($arrTalks) and count($arrTalks)>0) {
      foreach($arrTalks as $intTalkID=>$arrTalk) {
        $arrTalks[$intTalkID]['strTalkTitle']=stripslashes($arrTalk['strTalkTitle']);
        if(isset($arrAttendanceByTalks[$intTalkID])) {
          $arrTalks[$intTalkID]['intCount']=$arrAttendanceByTalks[$intTalkID];
        } else {
          $arrTalks[$intTalkID]['intCount']=0;
        }
        $arrTalks[$intTalkID]['strPresenter']=$arrPeopleAsPresentersOnly[$arrTalks[$intTalkID]['intPersonID']]['strName'];
        $arrTalks[$intTalkID]['strContactInfo']=$arrPeopleAsPresentersOnly[$arrTalks[$intTalkID]['intPersonID']]['strContactInfo'];
        $arrTalks[$intTalkID]['xsdStart']=$arrTalks[$intTalkID]['datTalk'] . 'T' . $this->arrTimeEndPoints[$arrTalks[$intTalkID]['intTimeID']]['s'] . $this->config['UTCOffset'];
        $arrTalks[$intTalkID]['xsdEnd']=$arrTalks[$intTalkID]['datTalk'] . 'T' . $this->arrTimeEndPoints[$arrTalks[$intTalkID]['intTimeID'] + ($arrTalks[$intTalkID]['intLength'] - 1)]['e'] . $this->config['UTCOffset'];
        $arrTalkSlots[$arrTalk['intTimeID']][$arrTalk['intRoomID']]=$intTalkID;
      }
      foreach($arrTalks as $intTalkID=>$arrTalk) {
        if($arrTalk['intLength']>1) {
          for($i=1; $i<=$arrTalk['intLength']-1; $i++) {
            if($arrTalkSlots[$arrTalk['intTimeID']+$i][$arrTalk['intRoomID']]>0) {
              $newRoom=0;
              $arrTalkSlots[$arrTalk['intTimeID']+$i][$newRoom]=$arrTalkSlots[$arrTalk['intTimeID']+$i][$arrTalk['intRoomID']];
              $arrTalkSlots[$arrTalk['intTimeID']+$i][$arrTalk['intRoomID']]=$intTalkID;
            } else {
              $arrTalkSlots[$arrTalk['intTimeID']+$i][$arrTalk['intRoomID']]=$intTalkID;
            }
          }
        }
      }
    } else {
      $arrTalks=array();
    }
    return array($arrTalkSlots, $arrTalks);
  }

  function sortRooms() {
    $this->doDebug("sortRooms();");
    if($this->today!=date('Y-m-d')) {return TRUE;}
    // Clearing Rooms.
    foreach($this->times as $intTimeID=>$arrTime) {
      foreach($this->rooms as $intRoomID=>$arrRoom) {
        $used_room[$intTimeID][$intRoomID]=0;
      }
    }
    list($this->arrTalkSlots, $this->arrTalks)=$this->readTalkData();
    foreach($this->arrTalks as $intTalkID=>$arrTalk) {
      if(!is_null($this->arrTalks[$intTalkID])) {
        if($this->arrTalks[$intTalkID]['boolFixed']==TRUE or $this->rooms[$this->arrTalks[$intTalkID]['intRoomID']]['boolIsDynamic']==FALSE) {
          $used_room[$this->arrTalks[$intTalkID]['intTimeID']][$this->arrTalks[$intTalkID]['intRoomID']]=$intTalkID;
          if($this->arrTalks[$intTalkID]['intLength']>1) {
            for($i=1; $i<$this->arrTalks[$intTalkID]['intLength']; $i++) {
              $used_room[$this->arrTalks[$intTalkID]['intTimeID']+$i][$this->arrTalks[$intTalkID]['intRoomID']]=$intTalkID;
            }
          }
        } else {
          $arrUnfixedTalks[$this->arrTalks[$intTalkID]['intTimeID']][$intTalkID]=$this->arrTalks[$intTalkID]['intCount'];
          if($this->arrTalks[$intTalkID]['intLength']>1) {
            for($i=1; $i<$this->arrTalks[$intTalkID]['intLength']; $i++) {$arrLongTalks[$this->arrTalks[$intTalkID]['intTimeID']+$i][$intTalkID]=$intTalkID;}
          }
        }
      }
    }
    foreach($this->times as $intTimeID => $arrTime) {
      $strTime = $arrTime['strTime'];
      $intTimeType = $arrTime['intTimeType'];
      if(isset($arrLongTalks[$intTimeID]) and count($arrLongTalks[$intTimeID])>0) {
        foreach($arrLongTalks[$intTimeID] as $intTalkID=>$null) {
          if($used_room[$intTimeID][$this->arrTalks[$intTalkID]['intRoomID']]==0) {
            $used_room[$intTimeID][$this->arrTalks[$intTalkID]['intRoomID']]=$intTalkID;
          }
        }
      }
      if(isset($arrUnfixedTalks[$intTimeID]) and count($arrUnfixedTalks[$intTimeID])>0) {
        unset($arrTalks);
        arsort($arrUnfixedTalks[$intTimeID]);
        foreach($arrUnfixedTalks[$intTimeID] as $intTalkID=>$null) {
          $arrTalks[]=$intTalkID;
        }
        reset($arrTalks);
        foreach($this->rooms as $intRoomID=>$arrRoom) {
          $intTalkID=0;
          if(current($arrTalks)!==FALSE) {$intTalkID=current($arrTalks);}
          if($used_room[$intTimeID][$intRoomID]==0 AND $intTalkID>0) {
            $used_room[$intTimeID][$intRoomID]=$intTalkID;
            $this->_setRoom($intRoomID, $intTalkID);
            $this->arrTalks[$intTalkID]['intRoomID']=$intRoomID;
            next($arrTalks);
          }
        }
      }
    }
  }

  function setSupportUser() {$_SESSION['support_user']=$this->intPersonID;}
  function getAuthCode() {
    $person=$this->getPerson(array('intPersonID'=>$this->intPersonID));
    return $person[$this->intPersonID]['strAuthString'];
  }

  function getContactDetails($intPersonID=0, $asArray=FALSE) {
    if($intPersonID==0) {$intPersonID=$this->intPersonID;}
    $this->doDebug("getContactDetails('$intPersonID', '$asArray')");
    if($asArray==FALSE) {$return='';} else {$return=array();}
    $people=$this->getPerson(array('intPersonID'=>$intPersonID));
    $contact_details=explode(" ", $people[$intPersonID]['strContactInfo']);
    foreach($contact_details as $cid=>$command) {
      foreach($this->contact_fields as $proto) {
        if(strpos($command, $proto)!==FALSE) {
          $proto_data=explode(':', $command);
          $real_data='';
          foreach($proto_data as $id=>$p_data) {
            if($id!=0) {
              if($real_data!='') {$real_data.=':';}
              $real_data.=$p_data;
            }
          }
          if($asArray==FALSE) {
            switch($proto) {
              case "mailto":
                if($real_data!='') {$data="<a href=\"mailto:$real_data\">e-mail</a> ";}
                break;
              case "twitter":
                if($real_data!='') {$data="<a href=\"http://twitter.com/$real_data\">twitter</a> ";}
                break;
              case "linkedin":
                if($real_data!='') {$data="<a href=\"http://linkedin.com/in/$real_data\">linked in</a> ";}
                break;
              case "identica":
                if($real_data!='') {$data="<a href=\"http://identi.ca/$real_data\">identi.ca</a> ";}
                break;
              case "statusnet":
                if($real_data!='') {$data="<a href=\"$real_data\">StatusNet</a> ";}
                break;
              case "facebook":
                if($real_data!='') {$data="<a href=\"http://facebook.com/$real_data\">facebook</a> ";}
                break;
              case "irc":
                if($real_data!='') {$data="<a href=\"irc://$real_data\">irc</a> ";}
                break;
              case "url":
                if($real_data!='') {$data="<a href=\"$real_data\">URL</a> ";}
                break;
              case "http":
                if($real_data!='') {$data="<a href=\"http://$real_data\">URL</a> ";}
                break;
              case "https":
                if($real_data!='') {$data="<a href=\"https://$real_data\">URL</a> ";}
                break;
            }
            if($return!='' AND $data!='') {$data.=' | ';}
            if(isset($data)) {$return.=$data;}
          } else {
            switch($proto) {
              case "mailto":
              case "twitter":
              case "linkedin":
              case "identica":
              case "statusnet":
              case "facebook":
              case "irc":
              case "url":
              case "http":
              case "https":
                if($real_data!='') {$return[$proto]=$real_data;}
                break;
            }
          }
        }
      }
    }
    if($asArray==TRUE) {$return['strName']=$people[$intPersonID]['strName'];}
    return $return;
  }

  function updateIdentityInfo($commands) {
    $this->doDebug("updateIdentityInfo(" . print_r($commands, TRUE) . ")");
    $contact_fields=array('mailto', 'email', 'twitter', 'linkedin', 'identica', 'statusnet', 'facebook', 'irc', 'url', 'http', 'https');
    $contact_name='';
    $contact_data='';
    foreach($commands as $cid=>$command) {
      $data='';
      $this->doDebug("Handling fragment $command", 2);
      if(strpos($command, ":")===FALSE) {
        $this->doDebug("Which isn't a protocol",3);
        if($contact_name!='') {$contact_name.=' ';}
        $contact_name.=$command;
      } else {
        $proto_data=explode(':', $command);
        $real_data='';
        foreach($proto_data as $id=>$p_data) {
          if($id==0) {
            $proto=$p_data;
          } else {
            if($real_data!='') {$real_data.=':';}
            $real_data.=$p_data;
          }
        }
        switch($proto) {
          case "mailto":
          case "email":
            if($real_data!='') {$data="mailto:$real_data";}
            break;
          case "twitter":
          case "linkedin":
          case "identi.ca":
          case "statusnet":
          case "facebook":
          case "irc":
          case "url":
          case "http":
          case "https":
            if($real_data!='') {$data="$proto:$real_data";}
            break;
        }
        if($contact_data!='' AND $data!='') {$contact_data.=' ';}
        $contact_data.=$data;
      }
    }
    if ($this->_updateIdentityInfo($contact_name, $contact_data)) {
      $this->updateStatusScreen("$contact_name updated their details on the system.");
      return true;
    }
  }

  protected function _updateIdentityInfo($contact_name, $contact_data) {
    $this->doDebug("_updateIdentityInfo('$contact_name, $contact_data')");
    if($this->boolUpdateOrInsertSql("UPDATE {$this->prefix}people SET strName='" . $this->escape($contact_name) . "', strContactInfo='" . $this->escape($contact_data) . "' WHERE intPersonID='{$this->intPersonID}'")) {
      $this->_addChange(array('intPersonID'=>$this->intPersonID));
      return true;
    } else {
      return false;
    }
  }

  function fixRooms() {
    $this->doDebug("fixRooms()");
    if(isset($this->config['FixRoomOffset'])) {$offset=$this->config['FixRoomOffset'];} else {$offset="-15 minutes";}
    $now_and_next=$this->getNowAndNextTime($offset);
    return $this->_fixRooms($now_and_next['now']);
  }

  protected function _fixRooms($now_time) {
    $this->doDebug("_fixRooms()");
    return $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}talks SET boolFixed=1 WHERE intTimeID<='$now_time'");
  }

  protected function _setAdmin() {
    $this->doDebug("_setAdmin()");
    $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}people SET boolIsAdmin=1 WHERE intPersonID='{$this->intPersonID}'");
    $this->generateNewAdminKey();
  }

  protected function _setSupport() {
    $this->doDebug("_setSupport()");
    $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}people SET boolIsSupport=1 WHERE intPersonID='{$this->intPersonID}'");
    $this->generateNewSupportKey();
  }

  function mergeContactDetails($strAuthString) {
    $strAuthString=$this->escape($strAuthString);
    if($this->config['adminkey']==$strAuthString) {
      $this->_setAdmin();
      return true;
    } elseif($this->config['supportkey']==$strAuthString) {
      $this->_setSupport();
      return true;
    } else {
      $contacts=$this->getPerson(array('strAuthString'=>$strAuthString));
      if(count($contacts)==1) {
        if ($this->_mergeContactDetails($contacts)) {
          return true;
        }
      }
    }
  }

  function allMyDetails() {
    $this->doDebug("allMyDetails()");
    $me = $this->getPerson(array('intPersonID'=>$this->intPersonID));
    foreach($me as $person) {}
    return $person;
  }

  function _mergeContactDetails($arrContacts) {
    $set = '';
    $this->doDebug("_mergeContactDetails(" . print_r($arrContacts, TRUE) . ")");

    $me = $this->allMyDetails();
    $state = false;
    foreach($arrContacts as $intContactID => $arrContact) {
      if($intContactID < $this->intPersonID) {
        $first = $arrContact;
        $second = $me;
      } else {
        $first = $me;
        $second = $arrContact;
      }
      $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}attendees SET intPersonID='{$first['intPersonID']}' WHERE intPersonID='{$second['intPersonID']}'");
      $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}sms_screen SET intPersonID='{$first['intPersonID']}' WHERE intPersonID='{$second['intPersonID']}'");
      $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}talks SET intPersonID='{$first['intPersonID']}' WHERE intPersonID='{$second['intPersonID']}'");

      if($second['strPhoneNumber']!='' AND $first['strPhoneNumber']=='') {$set="strPhoneNumber='{$second['strPhoneNumber']}'";}
      $second_is_more_up_to_date=0;
      foreach(array("Someone with a mobile number ending ", "A MicroBlogging user at ", "An OpenID User") as $userstrings) {
        if(substr($second['strName'], 0, strlen($userstrings))!=$userstrings) {$second_is_more_up_to_date=1;}
        if(substr($first['strName'], 0, strlen($userstrings))!=$userstrings) {$second_is_more_up_to_date=0;}
      }
      if($second_is_more_up_to_date) {if($set!='') {$set.=', ';} $set.="strName='{$second['strName']}'";}
      if($second['strContactInfo']!='' AND $first['strContactInfo']=='') {if($set!='') {$set.=', ';} $set.="strContactInfo='{$second['strContactInfo']}'";}
      if($second['strDefaultReply']!='' AND $first['strDefaultReply']=='') {if($set!='') {$set.=', ';} $set.="strDefaultReply='{$second['strDefaultReply']}'";}
      if($second['strOpenID']!='' AND $first['strOpenID']=='') {if($set!='') {$set.=', ';} $set.="strOpenID='{$second['strOpenID']}'";}
      if($second['strMicroBlog']!='' AND $first['strMicroBlog']=='') {if($set!='') {$set.=', ';} $set.="strMicroBlog='{$second['strMicroBlog']}'";}
      if($second['boolIsAdmin']!='0' AND $first['boolIsAdmin']==0) {if($set!='') {$set.=', ';} $set.="boolIsAdmin='{$second['boolIsAdmin']}'";}
      if($set != '') {
        $this->boolUpdateOrInsertSql("UPDATE {$this->prefix}people SET $set WHERE intPersonID='{$first['intPersonID']}'");
        $state = true;
      }
      $this->boolUpdateOrInsertSql("DELETE FROM {$this->prefix}people WHERE intPersonID='{$second['intPersonID']}'");
      $this->getMe(array("intPersonID"=>$first['intPersonID']));
      $this->refresh();
    }
    return $state;
  }

  function getAdmins() {
    $this->doDebug("getAdmins()");
    $return=$this->qryMap('intPersonID', 'count(boolIsAdmin)', "{$this->prefix}people WHERE boolIsAdmin!=0 LIMIT 1");
    if(count($return) == 0) {
      return(0);
    } else {
      return(1);
    }
  }
  function checkAdmin() {return($this->isAdmin);}

  function getSupport() {
    $this->doDebug("getSupport()");
    $return=$this->qryMap('intPersonID', 'count(boolIsSupport)', "{$this->prefix}people WHERE boolIsSupport!=0 LIMIT 1");
    if(count($return) == 0) {
      return(0);
    } else {
      return(1);
    }
  }

  function checkSupport() {return($this->isSupport);}

  function getSmsTemplate($sms_limit=50) {
    if(!isset($sms_limit)) {$sms_limit=50;}
    $this->doDebug("getSmsTemplate($sms_limit);");
    // Set Defaults
    $sms_list='';
    $messages=$this->showStatusScreen($sms_limit);

    if(count($messages)>0) {
      foreach($messages as $message) {$sms_list.=stripslashes(htmlentities($message)) . "<br />";}
    }
    return($sms_list);
  }

  function getTimetableTemplate($includeCountData, $includeProposeLink) {
    global $baseurl;
    // Set Defaults
    if(!isset($includeCountData)) {$includeCountData=TRUE;}
    if(!isset($includeProposeLink)) {$includeProposeLink=FALSE;}

    $this->doDebug("getTimetableTemplate($includeCountData, $includeProposeLink);");

    if(session_id()==='') {session_start();}
    if(isset($_SESSION['openid'])) {$this->getMe(array('OpenID'=>$_SESSION['openid'], 'OpenID_Name'=>CampUtils::arrayGet($_SESSION, 'name', ''), 'OpenID_Mail'=>CampUtils::arrayGet($_SESSION, 'email', '')));}

    // Get the talks this person is presenting
    $my_talks=$this->getMyTalks();
    // Get the talks this person is attending
    $attend_talks=$this->getTalksIAmAttending();
    $mainbody = '';
    if(count($this->rooms)>0) {
      $boolHasNonDynamicRooms = FALSE;
      $numNonDynamicRooms = 0;
      $boolAllNonDynamicRooms = FALSE;
      foreach($this->rooms as $intRoomID => $arrRoom) {
        if($arrRoom['boolIsDynamic']=='0') {
          $numNonDynamicRooms++;
          $boolHasNonDynamicRooms = TRUE;
        }
      }
      if(count($this->rooms)==$numNonDynamicRooms OR $this->config['dynamically_sort_whole_board_by_attendees']==0) {$boolAllNonDynamicRooms = TRUE;}

      $mainbody.="<table class=\"WholeDay\">\r\n";
      $mainbody.="  <thead>\r\n";
      $mainbody.="    <tr class=\"Time_title\">\r\n";
      $mainbody.="      <th class=\"Time_title\">&nbsp;</th>";
      foreach($this->times as $intTimeID => $arrTime) {
        $strTime = $arrTime['strTime'];
        $intTimeType = $arrTime['intTimeType'];
        if($intTimeID==$this->now_time and strtotime(date("Y-m-d"))==strtotime($this->today)) {$strTime.="<br />(On Now)";} elseif ($intTimeID==$this->next_time) {$strTime.="<br />(Next)";}
        $mainbody.="      <th class=\"Time_title\">Slot $intTimeID<br />$strTime";
        if($intTimeID>$this->now_time and $includeProposeLink==TRUE and $boolAllNonDynamicRooms==FALSE and strtotime(date("Y-m-d"))<=strtotime($this->today)) {
          $mainbody.="<br /><a href=\"?state=P&slot=$intTimeID\">New Talk</a>";
        }
        $mainbody.="</th>\r\n";
      }
      $mainbody.="    </tr>\r\n";
      $mainbody.="  </thead>\r\n";
      $mainbody.="  <tbody>\r\n";

      foreach($this->rooms as $intRoomID => $arrRoom) {
        $mainbody.="    <tr class=\"Room{$intRoomID}\">\r\n";
        $mainbody.="      <th class=\"Room{$intRoomID}\">";
        if($boolHasNonDynamicRooms==TRUE) {
          if($arrRoom['boolIsDynamic']=='0') {
            $mainbody.="Static<br /><br />";
          } else {
            $mainbody.="Dynamic<br /><br />";
          }
        }
        $mainbody.="{$arrRoom['strRoom']}</th>";
        foreach($this->times as $intTimeID => $arrTime) {
          $strTime = $arrTime['strTime'];
          $intTimeType = $arrTime['intTimeType'];
          if($this->arrTalkSlots[$intTimeID][$intRoomID]<=0) {
            if($intTimeID==$this->now_time) {$class=" Now";} elseif ($intTimeID==$this->next_time) {$class=" Next";}  else {$class="";}
            $mainbody.="<td class=\"Entry Time{$intTimeID}{$class}\">\r\n<table class=\"EntryBody\">\r\n<tr class=\"EntryBody\"><td class=\"EntryBody\">";
            if($this->arrTalkSlots[$intTimeID][$intRoomID]<0) {
              $mainbody.=$this->timetypes[-$this->arrTalkSlots[$intTimeID][$intRoomID]];
            } else {
              $mainbody.="Empty";
            }
            if(isset($this->intPersonID) and ($intTimeID > $this->now_time) and strtotime(date("Y-m-d"))<=strtotime($this->today)) {
              if($arrRoom['boolIsDynamic']=='0' or $this->config['dynamically_sort_whole_board_by_attendees']==0) {
                $mainbody.="<div class=\"label\"><a href=\"?state=S&slot=$intTimeID&room=$intRoomID\">New Talk</a></div>";
              }
            }
            $mainbody.="</td></tr></table></td>";
          } elseif($this->arrTalks[$this->arrTalkSlots[$intTimeID][$intRoomID]]['intTimeID']!=$intTimeID) {
            $mainbody.="      <!-- Continues from {$talk['intTalkID']} -->\r\n";
          } else {
            $talk=$this->arrTalks[$this->arrTalkSlots[$intTimeID][$intRoomID]];
            if($talk['boolFixed']==1) {$class="Time{$intTimeID} Fixed";} else {$class="Time{$intTimeID}";}
            if($talk['intLength']>1) {
              $colspan=" colspan=\"{$talk['intLength']}\"";
              for($c=1; $c<=count($talk['intLength']); $c++) {$class.=" Time" . ($intTimeID + $c);}
              $class.=" Long";
              $talk['TalkTitle'].=" ({$talk['intLength']} sessions long)";
            } else {$colspan='';}
            if($intTimeID==$this->now_time) {$class.=" Now";} elseif ($intTimeID==$this->next_time) {$class.=" Next";}
            $mainbody.="      <td class=\"Entry $class\"$colspan>\r\n";
            $mainbody.="        <table class=\"EntryBody\">\r\n";
            if($intTimeID>$this->now_time && $talk['intTalkID']!='') {
              $mainbody.="          <tr class=\"TalkID\"><td class=\"TalkID\"><span class=\"Label\">Talk Number:</span> <span class=\"Data\">{$talk['intTalkID']}</span></td></tr>\r\n";
            }
            $mainbody.="          <tr class=\"TalkTitle\"><td class=\"TalkTitle\"><span colspan=\"2\">\r\n";
            $mainbody.=htmlentities(stripslashes($talk['strTalkTitle'])) . "\r\n";
            if(isset($my_talks) && $intTimeID>$this->now_time && $talk['intTalkID']!='' && isset($my_talks[$talk['intTalkID']])) {
              $mainbody.="(<a href=\"$baseurl?state=C&talkid={$talk['intTalkID']}\" class=\"action\">Cancel</a> | <a href=\"$baseurl?state=E&talkid={$talk['intTalkID']}\" class=\"action\">Retitle</a>)";
            }
            $mainbody.="</span></td></tr>\r\n";
            if($talk['strPresenter']!='') {
              $mainbody.="          <tr class=\"Presenter\"><td class=\"Presenter\"><span class=\"Label Presenter\">By:</span> <span class=\"Data Presenter\">" . htmlentities(stripslashes($talk['strPresenter'])) . "</span></td></tr>\r\n";
            }
            if($talk['strContactInfo']!='') {
              $mainbody.="          <tr class=\"Contact\"><td class=\"Contact\"><span class=\"Label\">Contact:</span> <span class=\"Data\">" . $this->getContactDetails($talk['intPersonID']) . "</span></td></tr>\r\n";
            }
            if($talk['boolFixed']>0) {
              $mainbody.="          <tr class=\"Location\"><td class=\"Location\"><span class=\"Label\">Location:</span> <span class=\"Data\">{$this->rooms[$intRoomID]['strRoom']}</span></td></tr>\r\n";
            }
            if($talk['intCount']>$arrRoom['intCapacity']) {$countClass="Over";} else {$countClass="";}
            $mainbody.="          <tr class=\"Count\"><td class=\"Count\"><span class=\"Label\">Attending:</span> <span class=\"Data $countClass\">{$talk['intCount']}</span><td></tr>\r\n";
            if(isset($_SESSION['openid']) && $intTimeID>$this->now_time && $talk['intTalkID']!='' && isset($my_talks) && !isset($my_talks[$talk['intTalkID']]) && isset($attend_talks)) {
              $mainbody.="<tr class=\"Attend\"><td class=\"Attend\" colspan=\"2\">\r\n";
              if(!isset($attend_talks[$talk['intTalkID']])) {
                $mainbody.="<a href=\"$baseurl?state=A&talkid={$talk['intTalkID']}\" class=\"action\">Attend this talk.</a>\r\n";
              } else {
                $mainbody.="<a href=\"$baseurl?state=R&talkid={$talk['intTalkID']}\" class=\"action\">I'm attending.</a>\r\n";
              }
              $mainbody.="</td></tr>\r\n";
            }
            $mainbody.="        </table>\r\n";
            $mainbody.="      </td>\r\n";
          }
        }
        $mainbody.="    </tr>\r\n";
      }
      $mainbody.="</tbody>\r\n";
      $mainbody.="</table>\r\n";
    }
    return $mainbody;
  }

  function sendTimetable($only_mine = FALSE) {
    if($only_mine==FALSE) {
      $this->doDebug("sendTimetable(FALSE);");
    } else {
      $this->doDebug("sendTimetable(TRUE);");
    }

    // Get the talks this person is presenting
    $my_talks=$this->getMyTalks();
    // Get the talks this person is attending
    $attend_talks=$this->getTalksIAmAttending();
    $mainbody = '';
    if(count($this->rooms)>0) {
      foreach($this->times as $intTimeID => $arrTime) {
        if($intTimeID>=$this->now_time and $intTimeID<=($this->now_time+4)) {
          foreach($this->rooms as $intRoomID => $arrRoom) {
            if($this->arrTalkSlots[$intTimeID][$intRoomID]<=0) {
            } elseif($this->arrTalks[$this->arrTalkSlots[$intTimeID][$intRoomID]]['intTimeID']!=$intTimeID) {
            } else {
              $talk=$this->arrTalks[$this->arrTalkSlots[$intTimeID][$intRoomID]];
              if($talk['intLength']>1) {
                $talk['TalkTitle'].=" ({$talk['intLength']} slots long)";
              }
              if($only_mine==FALSE) {
                if($mainbody!='') {$mainbody.=' | ';}
                $mainbody.="At " . $this->arrTimeEndPoints[$intTimeID]['s'] . " - " . $talk['strTalkTitle'] . " (" . $arrRoom['strRoom']  . ")";
              } elseif($only_mine==TRUE and (isset($my_talks[$talk['intTalkID']]) or isset($attend_talks[$talk['intTalkID']]))) {
                if($mainbody!='') {$mainbody.=' | ';}
                $mainbody.="At " . $this->arrTimeEndPoints[$intTimeID]['s'] . " - " . $talk['strTalkTitle'] . " (" . $arrRoom['strRoom']  . ")";
              }
            }
          }
        }
      }
    }
    $this->sendMessage($mainbody);
  }

  function getDirectionTemplate() {
    // Here is our list of directions.
    $directions=array("UL", "U", "UR", "L", "C", "R", "DL", "D", "DR");

    // Now map the screen information against the directions to rooms
    $room_directions=$this->getDirections();

    // Check whether all the rooms have a direction listed.
    $setroom=FALSE;
    foreach($this->rooms as $intRoomID=>$room) {
      if(!isset($room_directions[$intRoomID])) {
        $setroom=TRUE;
      }
    }

    foreach($directions as $direction) {
      switch($direction) {
        case "C":
          if($setroom==TRUE) {$d['C']="Please define<br />which direction<br />your rooms are<br />in from this screen.";} else {$d['C']="    Directions to the talks\r\n";}
          break;
        default:
          $rooms_in_direction=array_keys($room_directions, $direction);
          $d[$direction]='';
          if(count($rooms_in_direction)>0) {
            foreach(array_keys($room_directions, $direction) as $room_id) {
              if($setroom==FALSE) {
                $d[$direction].="      <span class=\"RoomName\">{$this->rooms[$room_id]['strRoom']}</span><br />\r\n";
                if($this->now_time=='' OR $this->now_time==0 OR $this->arrTalkSlots[$this->now_time][$room_id]==0 OR $this->arrTalkSlots[$this->now_time][$room_id]==-1) {$talk="Empty";} else {
                  $talk=$this->arrTalks[$this->arrTalkSlots[$this->now_time][$room_id]]['strTalkTitle'];
                  if($this->arrTalks[$this->arrTalkSlots[$this->now_time][$room_id]]['intTalkID']!=$this->arrTalkSlots[$this->now_time][$room_id]) {$talk." (continued)";}
                  $talk.=" by " . $this->arrTalks[$this->arrTalkSlots[$this->now_time][$room_id]]['strPresenter'];
                }
                $d[$direction].="      <span class=\"Now\">On Now: $talk</span><br />\r\n";
                if($this->next_time=='' OR $this->next_time==0 OR $this->arrTalkSlots[$this->next_time][$room_id]==0 OR $this->arrTalkSlots[$this->next_time][$room_id]==-1) {$talk="Empty";} else {
                  $talk=$this->arrTalks[$this->arrTalkSlots[$this->next_time][$room_id]]['strTalkTitle'];
                  if($this->arrTalks[$this->arrTalkSlots[$this->next_time][$room_id]]['intTalkID']!=$this->arrTalkSlots[$this->next_time][$room_id]) {$talk." (continued)";}
                  $talk.=" by " . $this->arrTalks[$this->arrTalkSlots[$this->next_time][$room_id]]['strPresenter'];
                }
                $d[$direction].="      <span class=\"Next\">On Next: $talk</span><br />\r\n";
              } else {
                $d[$direction].="      <span class=\"Direction_Header\">Rooms defined in this direction:</span><br />\r\n";
                foreach(array_keys($room_directions, $direction) as $room_id) {$d[$direction].="      <span class=\"Room_In_Direction\">{$this->rooms[$room_id]['strRoom']}</span><br />\r\n";}
                $d[$direction].="      <span class=\"Direction_Header\">Rooms still to be defined:</span><br />\r\n";
                foreach($this->rooms as $room_id=>$room) {
                  if(!isset($room_directions[$room_id])) {
                    $d[$direction].="      <span class=\"Room_to_define\"><a href=\"?setroomdir=true&roomno=$room_id&roomdir=$direction\">{$this->rooms[$room_id]['strRoom']}</a></span><br />\r\n";
                  }
                }
              }
            }
          } else {
            if($setroom==TRUE) {
              $d[$direction].="      <span class=\"Direction_Header\">Rooms still to be defined:</span><br />\r\n";
              foreach($this->rooms as $room_id=>$room) {
                if(!isset($room_directions[$room_id])) {
                  $d[$direction].="      <span class=\"Room_to_define\"><a href=\"?setroomdir=true&roomno=$room_id&roomdir=$direction\">{$this->rooms[$room_id]['strRoom']}</a></span><br />\r\n";
                }
              }
            }
          }
          break;
      }
    }
    return $d;
  }
}
