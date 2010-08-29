<?php
/*******************************************************
 * CampFireManager
 * Admin Console
 * Version 0.5 2010-03-19 JonTheNiceGuy
 *******************************************************
 * Version History
 * 0.5 2010-03-19 Migrated from personal SVN store
 * at http://spriggs.org.uk/jon_code/CampFireManager
 * where all prior versions are stored to Google Code at 
 * http://code.google.com/p/campfiremanager/
 ******************************************************/

if(session_id()==='') {session_start();}
if(isset($_SESSION['redirect'])) {unset($_SESSION['redirect']);}
require_once("db.php");

echo "<!-- " . print_r($_REQUEST, TRUE) . " -->\r\n";

if(!isset($Camp_DB->config['adminkey'])) {$Camp_DB->generateNewAdminKey();}
if(!isset($Camp_DB->config['supportkey'])) {$Camp_DB->generateNewSupportKey();}
// You're only allowed here if you've already logged in
if(!isset($_SESSION['openid'])) {
  $_SESSION['redirect']='admin.php';
  header("Location: $baseurl");
} else {
  $dataSet = array(
    'OpenID'=>$_SESSION['openid'],
    'OpenID_Name'=>CampUtils::arrayGet($_SESSION, 'name', ''),
    'OpenID_Mail'=>CampUtils::arrayGet($_SESSION, 'email', '')
  );
  $Camp_DB->getMe($dataSet);
}
if($Camp_DB->getAdmins()==0) { // If there's no-one here yet, you get it by default!!
  header("Location: $baseurl?state=Oa&AuthString={$Camp_DB->config['adminkey']}");
} elseif($Camp_DB->checkAdmin()==1) { // Otherwise you'll only get it if you're in the admin list
  $config_fields=array('admin_regen'=>'',
                       'support_regen'=>'',
                       'website'=>"The public URL of this site. Leave blank if you don't want public access.",
                       'event_title'=>'What is the title of your event?',
                       'FixRoomOffset'=>'Relative to the start time of a session, at what point is the room allocated to a talk fixed?',
                       'UTCOffset'=>'The UTC offset for the timezone, e.g. +00:00 for GMT or -08:00 for Pacific Standard Time.',
                       'timezone_name'=>'This is the name of your timezone (e.g. Europe/London)',
                       'AboutTheEvent'=>'Please provide some details about the content of your event.',
                       'hashtag'=>"Optional: What do you want people (including this script) to use as the hashtag for today?, including the # sign itself.",
                       'sessions_fixed_to_one_slot'=>"Do you want your sessions to be limited to only one slot?",
                       'dynamically_sort_whole_board_by_attendees'=>'',
                       'require_contact_details'=>''
    );

  if(isset($_POST['update_config'])) {
    foreach($config_fields as $field=>$strTime) {
      $Camp_DB->setConfig($field, stripslashes(CampUtils::arrayGet($_POST, $field, '')));
    }
  }
  if(isset($_POST['update_times'])) {
    $Camp_DB->boolUpdateOrInsertSql("TRUNCATE {$Camp_DB->prefix}times");
    foreach($_POST as $key=>$value) {
      if('time_'==substr($key, 0, 5)) {
        $Camp_DB->updateTime('', trim($value), $_POST['break_' . substr($key, 5)]);
      }
    }
    $Camp_DB->refresh();
  }
  if(isset($_POST['update_time_types'])) {
    $Camp_DB->boolUpdateOrInsertSql("TRUNCATE {$Camp_DB->prefix}timetypes");
    foreach($_POST as $key=>$value) {
      if('timetype_'==substr($key, 0, 9)) {
        $Camp_DB->updateTimeType('', trim($value));
      }
    }
    $Camp_DB->refresh();
  }
  if(isset($_POST['update_rooms'])) {
    foreach($Camp_DB->rooms as $value=>$strTime) {
      $Camp_DB->updateRoom($value, $_POST['room_' . $value], $_POST['capacity_' . $value], $_POST['dynamic_' . $value]);
    }
    if($_POST['room_new']!='' AND $_POST['capacity_new']!='') {
      $Camp_DB->updateRoom('', $_POST['room_new'], $_POST['capacity_new'], $_POST['dynamic_new']);
    }
  }
  if(!isset($Camp_DB->config['adminkey'])) {$Camp_DB->generateNewAdminKey();}
  if(isset($_POST['update_phones'])) {
    $arrPhones=$Camp_DB->getPhones();
    foreach($arrPhones as $intPhoneID=>$arrPhone) {$Camp_DB->updatePhone($intPhoneID, $_POST['phone_number_' . $intPhoneID], $_POST['phone_network_' . $intPhoneID], $_POST['phone_gammu_' . $intPhoneID]);}
    if($_POST['phone_number_new']!='' AND $_POST['phone_network_new']!='' AND $_POST['phone_gammu_new']!='') {$Camp_DB->updatePhone('', $_POST['phone_number_new'], $_POST['phone_network_new'], $_POST['phone_gammu_new']);}
  }
  if(isset($_POST['update_microblogs'])) {
    $arrMbs=$Camp_DB->getMicroBloggingAccounts();
    foreach($arrMbs as $intMbID=>$arrMb) {$Camp_DB->updateMb($intMbID, $_POST['mb_api_' . $intMbID], $_POST['mb_user_' . $intMbID], $_POST['mb_pass_' . $intMbID]);}
    if($_POST['mb_api_new']!='' AND $_POST['mb_user_new']!='' AND $_POST['mb_pass_new']!='') {$Camp_DB->updateMb('', $_POST['mb_api_new'], $_POST['mb_user_new'], $_POST['mb_pass_new']);}
  }
  $Camp_DB->refresh();
  $arrPhones=$Camp_DB->getPhones();
  $arrMbs=$Camp_DB->getMicroBloggingAccounts();
  $arrTimeTypes = $Camp_DB->getTimeTypes();
  $event_title =CampUtils::arrayGet($Camp_DB->config, 'event_title', 'CampfireDefaultEvent');
  echo "<html>
<head>
<title>$event_title</title>
<link rel=\"stylesheet\" type=\"text/css\" href=\"common_style.php\" />
</head>
<body>
<form method=\"post\" action=\"{$baseurl}admin.php\" class=\"WholeDay\">
<input type=\"hidden\" name=\"update_config\" value=\"TRUE\">
<table>
  <tr><td><a href=\"$baseurl\" class=\"Label\">Back to main screen</a></td></tr>
  <tr><th colspan=\"2\">Admin Console for Config Options (empty boxes will unset those values in the database)</th></tr>";
  foreach($config_fields as $value=>$data) {
    $valueStr = CampUtils::arrayGet($Camp_DB->config, $value, '');
    switch($value) {
      case 'admin_regen':
        if(1==CampUtils::arrayGet($Camp_DB->config, $value, 1)) {
          $yes="checked";
          $no="";
        } else {
          $yes="";
          $no="checked";
        }
        echo "<tr><td class=\"Label\">Next Admin Key</td><td class=\"Data\">{$Camp_DB->config['adminkey']}</td></tr>";
        echo "<tr><td class=\"Label\">Should the admin key regenerate each time it's used?</td><td class=\"Data\">";
        echo "<input type=\"radio\" name=\"$value\" value=\"1\" $yes> Yes ";
        echo "<input type=\"radio\" name=\"$value\" value=\"0\" $no> No";
        echo "</td></tr>";
        break;
      case 'support_regen':
        if(1==CampUtils::arrayGet($Camp_DB->config, $value, 1)) {
          $yes="checked";
          $no="";
        } else {
          $yes="";
          $no="checked";
        }
        echo "<tr><td class=\"Label\">Next Support Key</td><td class=\"Data\">{$Camp_DB->config['supportkey']}</td></tr>";
        echo "<tr><td class=\"Label\">Should the support key regenerate each time it's used?</td><td class=\"Data\">";
        echo "<input type=\"radio\" name=\"$value\" value=\"1\" $yes> Yes ";
        echo "<input type=\"radio\" name=\"$value\" value=\"0\" $no> No";
        echo "</td></tr>";
        break;
      case 'require_contact_details':
        if(1==CampUtils::arrayGet($Camp_DB->config, $value, 1)) {
          $yes="checked";
          $no="";
        } else {
          $yes="";
          $no="checked";
        }
        echo "<tr><td class=\"Label\">Will we require OpenID Users to rename themselves from 'An OpenID User'?</td><td class=\"Data\">";
        echo "<input type=\"radio\" name=\"$value\" value=\"1\" $yes> Yes ";
        echo "<input type=\"radio\" name=\"$value\" value=\"0\" $no> No";
        echo "</td></tr>";
        break;
      case 'dynamically_sort_whole_board_by_attendees':
        if(1==CampUtils::arrayGet($Camp_DB->config, $value, 1)) {
          $yes="checked";
          $no="";
        } else {
          $yes="";
          $no="checked";
        }
        echo "<tr><td class=\"Label\">Will all the rooms auto-sort talks into slots based on the number of attendees?</td><td class=\"Data\">";
        echo "<input type=\"radio\" name=\"$value\" value=\"1\" $yes> Yes ";
        echo "<input type=\"radio\" name=\"$value\" value=\"0\" $no> No";
        echo "</td></tr>";
        break;
      case 'sessions_fixed_to_one_slot':
        if(1==CampUtils::arrayGet($Camp_DB->config, $value, 0)) {
          $yes="checked";
          $no="";
        } else {
          $yes="";
          $no="checked";
        }
        echo "<tr><td class=\"Label\">$data</td><td class=\"Data\">";
        echo "<input type=\"radio\" name=\"$value\" value=\"1\" $yes> Yes ";
        echo "<input type=\"radio\" name=\"$value\" value=\"0\" $no> No";
        echo "</td></tr>";
        break;
      default:
        echo "  <tr><td class=\"Label\">$data</td><td class=\"Data\"><input type=\"text\" name=\"$value\" size=\"25\" value=\"$valueStr\"></td></tr>";
    }
  }
  echo "<tr><td colspan=\"2\"><input type=\"submit\" value=\"Update Configuration\"></form>";
  echo "
<tr><td><form method=\"post\" action=\"{$baseurl}admin.php#sms\" class=\"WholeDay\">
<input type=\"hidden\" name=\"update_phones\" value=\"TRUE\">
<table>
  <tr><th colspan=\"3\">SMS Devices (accessed by Gammu)</th></tr>
  <tr><th>Phone Number</th><th>Phone Network</th><th>SMS Engine Identifier</th></tr>";
  foreach($arrPhones as $intPhoneID=>$arrPhone) {echo "
  <tr>
    <td class=\"Data\"><input type=\"text\" name=\"phone_number_$intPhoneID\" size=\"15\" value=\"{$arrPhone['strNumber']}\"></td>
    <td class=\"Data\"><input type=\"text\" name=\"phone_network_$intPhoneID\" size=\"15\" value=\"{$arrPhone['strPhone']}\"></td>
    <td class=\"Data\"><input type=\"text\" name=\"phone_gammu_$intPhoneID\" size=\"15\" value=\"{$arrPhone['strGammuRef']}\"></td>
  </tr>";}
  echo "
  <tr><th colspan=\"3\">New SMS Device</th></tr>
  <tr>
    <td class=\"Data\"><a name=\"sms\"><input type=\"text\" name=\"phone_number_new\" size=\"15\" value=\"\"></td>
    <td class=\"Data\"><input type=\"text\" name=\"phone_network_new\" size=\"15\" value=\"\"></td>
    <td class=\"Data\"><input type=\"text\" name=\"phone_gammu_new\" size=\"15\" value=\"\"></td>
  </tr>";
  echo "<tr><td colspan=\"3\"><input type=\"submit\" value=\"Update Phones\">";
  echo "</table></form></td>
<td><form method=\"post\" action=\"{$baseurl}admin.php#mb\" class=\"WholeDay\">
<input type=\"hidden\" name=\"update_microblogs\" value=\"TRUE\">
<table>
  <tr><th colspan=\"3\">Microbloggging Accounts (via Twitter APIs)</th></tr>
  <tr><th>API</th><th>Username</th><th>Password</th></tr>";
  foreach($arrMbs as $intMbID=>$arrMb) {echo "
  <tr>
    <td class=\"Data\"><input type=\"text\" name=\"mb_api_$intMbID\" size=\"15\" value=\"{$arrMb['strApiBase']}\"></td>
    <td class=\"Data\"><input type=\"text\" name=\"mb_user_$intMbID\" size=\"15\" value=\"{$arrMb['strAccount']}\"></td>
    <td class=\"Data\"><input type=\"password\" name=\"mb_pass_$intMbID\" size=\"15\" value=\"{$arrMb['strPassword']}\"></td>
  </tr>";}
  echo "
  <tr><th colspan=\"4\">New Microblog</th></tr>
  <tr>
    <td class=\"Data\"><a name=\"mb\"><input type=\"text\" name=\"mb_api_new\" size=\"15\" value=\"\"></td>
    <td class=\"Data\"><input type=\"text\" name=\"mb_user_new\" size=\"15\" value=\"\"></td>
    <td class=\"Data\"><input type=\"password\" name=\"mb_pass_new\" size=\"15\" value=\"\"></td>
  </tr>";
  echo "<tr><td colspan=\"4\"><input type=\"submit\" value=\"Update Microblogs\">";
  echo "</table></form></td>
<tr><td><form method=\"post\" action=\"{$baseurl}admin.php#time\" class=\"WholeDay\">
<input type=\"hidden\" name=\"update_times\" value=\"TRUE\">
<table>
  <tr><th colspan=\"3\">Time Options (please sort these manually by time)</th></tr>
  <tr><th>Time Slot</th><th>Period in the format HH:MM-HH:MM</th><th>Break type</th></tr>";
  foreach($Camp_DB->times as $intTimeID=>$arrTime) {
    $strTime = $arrTime['strTime'];
    $intTimeType = $arrTime['intTimeType'];
    $break_options='<option value="0">Empty</option>';
    if(is_array($arrTimeTypes) and count($arrTimeTypes)>0) {
      foreach($arrTimeTypes as $intTimeTypeID => $strTimeType) {
        if($intTimeTypeID == $intTimeType) {$selected = 'selected="selected"';} else {$selected = '';}
        $break_options.='<option value="' . $intTimeTypeID . '" ' . $selected . '>' . $strTimeType . '</option>';
      }
    }
    echo "
  <tr>
    <td class=\"Label\">$intTimeID</td>
    <td class=\"Data\"><input type=\"text\" name=\"time_$intTimeID\" size=\"10\" value=\"$strTime\"></td>
    <td class=\"Data\"><select name=\"break_$intTimeID\">$break_options</select></td>
  </tr>";
  }
  $break_options='<option value="0">Empty</option>';
  if(is_array($arrTimeTypes) and count($arrTimeTypes)>0) {
    foreach($arrTimeTypes as $intTimeTypeID => $strTimeType) {
      $break_options.='<option value="' . $intTimeTypeID . '">' . $strTimeType . '</option>';
    }
  }
  echo "
  <tr>
    <td class=\"Label\">New Time Slot</td>
    <td class=\"Data\"><a name=\"time\"><input type=\"text\" name=\"time_new\" size=\"10\" value=\"\"></td>
    <td class=\"Data\"><select name=\"break_new\">$break_options</select></td>
  </tr>";
  echo "<tr><td colspan=\"3\"><input type=\"submit\" value=\"Update Times\">";
  echo "</table></form></td>
<td><form method=\"post\" action=\"{$baseurl}admin.php#room\" class=\"WholeDay\">
<input type=\"hidden\" name=\"update_rooms\" value=\"TRUE\">
<table>
  <tr><th colspan=\"3\">Room Options (please sort these manually by capacity)</th></tr>
  <tr><th>Room ID</th><th>Name</th><th>Capacity</th><th>Dynamic Sorting</th></tr>";
  foreach($Camp_DB->rooms as $roomid=>$room) {
    if('1'==CampUtils::arrayGet($room, 'boolIsDynamic', '1')) {
      $no="";
      $yes="selected";
    } else {
      $no="selected";
      $yes="";
    }
    echo "
  <tr>
    <td class=\"Label\">Room $roomid</td>
    <td class=\"Data\"><input type=\"text\" name=\"room_$roomid\" size=\"25\" value=\"{$room['strRoom']}\"></td>
    <td class=\"Data\"><input type=\"text\" name=\"capacity_$roomid\" size=\"4\" value=\"{$room['intCapacity']}\"></td>
    <td class=\"Data\">
      <select name=\"dynamic_$roomid\">
        <option value=\"1\" $yes>Yes</option>
        <option value=\"0\" $no>No</option>
      </select>
    </td>
  </tr>";}
  echo "
  <tr>
    <td class=\"Label\">New Room</td>
    <td class=\"Data\"><a name=\"room\"><input type=\"text\" name=\"room_new\" size=\"25\" value=\"\"></td>
    <td class=\"Data\"><input type=\"text\" name=\"capacity_new\" size=\"4\" value=\"\"></td>
    <td class=\"Data\">
      <select name=\"dynamic_new\">
        <option value=\"1\" selected>Yes</option>
        <option value=\"0\">No</option>
      </select>
    </td>
  </tr>";
  echo "<tr><td colspan=\"3\"><input type=\"submit\" value=\"Update Configuration\">";
  echo "</table></form></td></tr>";
  echo "<tr><td><form method=\"post\" action=\"{$baseurl}admin.php#timetype\" class=\"WholeDay\">
<input type=\"hidden\" name=\"update_time_types\" value=\"TRUE\">
<table>
  <tr><th colspan=\"2\">Break Types</th></tr>
  <tr><th>Break ID</th><th>Break type</th></tr>";
  if(is_array($Camp_DB->timetypes) and count($Camp_DB->timetypes)>0) {
    foreach($Camp_DB->timetypes as $intTimeTypeID=>$strTimeType) {
      echo "
  <tr>
    <td class=\"Label\">$intTimeTypeID</td>
    <td class=\"Data\"><input type=\"text\" name=\"timetype_$intTimeTypeID\" size=\"10\" value=\"$strTimeType\"></td>
  </tr>";
    }
  }
  echo "
  <tr>
    <td class=\"Label\">New Break Type</td>
    <td class=\"Data\"><a name=\"timetype\"><input type=\"text\" name=\"timetype_new\" size=\"10\" value=\"\"></td>
  </tr>";
  echo "<tr><td colspan=\"2\"><input type=\"submit\" value=\"Update Times\">";
  echo "</table></form></td>";
  echo "<td>&nbsp;</td></tr>";
  echo "</table>";
} else {
  header("Location: $baseurl");
}
