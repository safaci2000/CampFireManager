<?php
/*******************************************************
 * CampFireManager
 * Public facing web page
 * Version 0.5 2010-03-19 JonTheNiceGuy
 *******************************************************
 * Version History
 * 0.5 2010-03-19 Migrated from personal SVN store
 * at http://spriggs.org.uk/jon_code/CampFireManager
 * where all prior versions are stored to Google Code at
 * http://code.google.com/p/campfiremanager/
 ******************************************************/

if (session_id()==='') {
    $lifetime=604800; // 7 Days
    session_start();
    setcookie(session_name(),session_id(),time()+$lifetime);
}
if(isset($_SESSION['openid']) and isset($_SESSION['redirect'])) {header("Location: " . $_SESSION['redirect']);}
require_once("db.php");
require_once("{$base_dir}common_functions-template.php");
require_once("{$base_dir}common_xajax.php");

switch(CampUtils::arrayGet($_REQUEST,'state','')) {
  case 'logout':
    $err="You have successfully logged out. If you want to act further, please try again. <br />";
    foreach($_SESSION as $key=>$val) {unset($_SESSION[$key]);}
  break;
  case 'fail':
    $err="There was a problem logging you in with these details. Please try again.<br />";
    foreach($_SESSION as $key=>$val) {unset($_SESSION[$key]);}
  break;
  case 'cancel':
    $err="You clicked on cancel. Please try again.<br />";
    foreach($_SESSION as $key=>$val) {unset($_SESSION[$key]);}
  break;
}

if(isset($_SESSION['openid'])) {
  $Camp_DB->getMe(array('OpenID'=>$_SESSION['openid'], 'OpenID_Name'=>CampUtils::arrayGet($_SESSION, 'name', ''), 'OpenID_Mail'=>CampUtils::arrayGet($_SESSION, 'email', '')));
  if($Camp_DB->checkAdmin()!=0 and isset($_GET['admin_use_date'])) {
    if($_GET['admin_use_date']=='') {$_GET['admin_use_date']='today';}
    $_SESSION['today']=date("Y-m-d", strtotime($_GET['admin_use_date']));
    $Camp_DB->refresh();
  }
  $details=$Camp_DB->getContactDetails(0, TRUE);
  switch(CampUtils::arrayGet($_REQUEST, 'state', '')) {
    case "I":
    case "Id":
      break;
    default:
    if('An OpenID User'==CampUtils::arrayGet($details, 'strName', 'An OpenID User') and '1'==CampUtils::arrayGet($Camp_DB->config, 'require_contact_details', 0)) {
      header("Location: $baseurl?state=I");
    }
  }
}

// Find the "Now" and "Next" time blocks
$now_and_next=$Camp_DB->getNowAndNextTime();
$now=$now_and_next['now'];
$next=$now_and_next['next'];

$contact_fields=array('mailto', 'twitter', 'linkedin', 'identica', 'statusnet', 'facebook', 'irc', 'http', 'https');
$event_title = CampUtils::arrayGet($Camp_DB->config, 'event_title', 'an undefined event');
$event_details = CampUtils::arrayGet($Camp_DB->config, 'AboutTheEvent',  'Event details go here.');

 ?>
<html>
  <head>
  <title><?php echo $event_title; ?></title>
  <link rel="stylesheet" type="text/css" href="common_style.php" />
  <link rel="openid.server" href="<?php echo $baseurl; ?>/openid_server/" />
  <script type="text/javascript" src="external/jquery-1.4.2.min.js"></script>
  <script type="text/javascript" src="external/jquery.marquee.js"></script>
  <?php $xajax->printJavascript(); ?>

  <script type="text/javascript">
    $(document).ready(function(){
      $('.HideWithJS').hide('fast');
      $('.RespondToAction').fadeOut(4000, function() {
        $(this).remove();
      });
    });
    function update() {
      xajax_ajaxPopulateTable();
      setTimeout("update()", 20000);
    }
  </script>
  </head>
  </style>
  <body>
<?php

if(!isset($_SESSION['openid'])) {
  echo "<h1>Login to CampFireManager for $event_title</h1>";
  if(isset($err)) {echo '<div id="verify-form" class="error">'.$err.'</div>';}
  if(isset($_GET['reason'])) {echo '<div id="verify-form" class="error">Reason: ' . $_GET['reason'] . '</div>';}
  echo '<div id="verify-form"><table width="100%"><tr>
  <td>Please select your OpenID provider from these icons:
    <table width="100%">
      <tr>
        <td>
          <form method="get" action="try_auth.php">
            <input type="hidden" name="action" value="verify" />
            <input type="hidden" name="openid_identifier" value="https://www.google.com/accounts/o8/id" />
            <input type="image" src="images/google.png" alt="Sign in using your Google Account" />
          </form>
        </td>
        <td>
          <form method="get" action="try_auth.php">
            <input type="hidden" name="action" value="verify" />
            <input type="hidden" name="openid_identifier" value="http://yahoo.com" />
            <input type="image" src="images/yahoo.png" alt="Sign in using your Yahoo Account" />
          </form>
        </td>
        <td>
          <form method="get" action="try_auth.php">
            <input type="hidden" name="action" value="verify" />
            <input type="hidden" name="openid_identifier" value="http://myspace.com" />
            <input type="image" src="images/myspace.png" alt="Sign in using your MySpace Account" />
          </form>
        </td>
      </tr>
    </table>
  </td>
  <td>Or enter your own below:
    <br />
    <form method="get" action="try_auth.php">
      <input type="hidden" name="action" value="verify" />
      <input type="text" name="openid_identifier" size="25" value="" />
      <input type="submit" value="Log in" />
    </form>
  </td>
</tr>
</table>
</div>';
  echo "<div class=\"EventDetails\">$event_details</div>";
  if(!isset($_SESSION['redirect'])) {
    echo '<div id="mainbody" class="mainbody"></div><script type="text/javascript">update();</script>';
  }
} else {
  echo "<h1 class=\"headerbar\">$event_title</h1>\r\n";
  switch(CampUtils::arrayGet($_REQUEST, 'state', '')) {
    case "O":
      $arrAuthString=$Camp_DB->getAuthStrings();
      $phones=$Camp_DB->getPhones();
      $omb=$Camp_DB->getMicroBloggingAccounts();
      $phone_numbers='';
      foreach($phones as $phone) {
        if($phone_numbers!='') {$phone_numbers.=", ";}
        $phone_numbers.="{$phone['strNumber']}";
      }
      $omb_accounts='';
      foreach($omb as $omb_ac) {
        if($omb_accounts!='') {$omb_accounts.=", ";}
        $omb_server=
        $omb_accounts.="@{$omb_ac['strAccount']} on " . parse_url($omb_ac['strApiBase'], PHP_URL_HOST);
      }
      echo "\r\n<form method=\"post\" action=\"$baseurl\" class=\"DrawAttention\">\r\n<input type=\"hidden\" name=\"state\" value=\"Oa\">Please send the following to ";
      if(count($phones)==1) {echo "this number ($phone_numbers)";}
      if(count($phones)>1) {echo "one of these numbers ($phone_numbers)";}
      if(count($phones)>0 and count($omb)>0) {echo " or ";}
      if(count($omb)==1) {echo "$omb_accounts by direct message";}
      if(count($omb)>1) {echo "your preferred microblogging account (from $omb_accounts by) direct message";}
      echo ":\r\nO " . $Camp_DB->getAuthCode() . "<br />\r\nAlternatively, please enter another Auth String into this box:<input type=\"text\" size=\"25\" name=\"AuthString\" />\r\n<input type=\"submit\" value=\"Go\"/> <a href=\"$baseurl\">Or, click here if you changed your mind.</a>\r\n</form>";
      break;
    case "Oa":
      echo "<p class=\"RespondToAction\">Adding an Authorization String to your account</p>\r\n";
      $Camp_DB->mergeContactDetails($_REQUEST['AuthString']);
      break;
    case "I":
      $details=$Camp_DB->getContactDetails(0, TRUE);
      echo "\r\n<form method=\"post\" action=\"$baseurl\" class=\"DrawAttention\">\r\n" .
                "<input type=\"hidden\" name=\"state\" value=\"Id\">";
      echo "<table width=\"100%\"><tr><td colspan=\"3\" class=\"Label DrawAttention Right\">Name:</td><td colspan=\"3\" class=\"Data Left\"><input type=\"text\" name=\"name\" value=\"" . CampUtils::arrayGet($details, 'strName', '') . "\" /></td></tr>";
      $intCol=0;
      foreach($contact_fields as $proto) {
        if($intCol==0) {echo "<tr>";}
        echo "\r\n<td class=\"Label DrawAttention Right\">$proto:</td><td class=\"Data Left\"><input type=\"text\" name=\"$proto\" value=\"" . CampUtils::arrayGet($details, $proto, '') . "\" /></td>";
        $intCol++;
        if($intCol==3) {
          echo "</tr>";
          $intCol=0;
        }
      }
      if($intCol!=0) {
        while($intCol<3) {
          echo "<td>&nbsp;</td><td>&nbsp;</td>";
          $intCol++;
        }
        echo "</tr>";
      }
      echo "</table>";
      echo "\r\n<input type=\"submit\" value=\"Go\"/> <a href=\"$baseurl\">Or, click here if you changed your mind.</a>\r\n</form>";
      break;
    case "Id":
      echo "<p class=\"RespondToAction\">Updating your contact information</p>\r\n";
      $data=array();
      $data[]=$Camp_DB->escape($_REQUEST['name']);
      foreach($contact_fields as $proto) {if(isset($_REQUEST[$proto])) {$data[]=$proto . ":" . $Camp_DB->escape($_REQUEST[$proto]);}}
      $Camp_DB->updateIdentityInfo($data);
      break;
    case "P":
      echo "\r\n<form method=\"post\" action=\"$baseurl\" class=\"DrawAttention\">\r\n<input type=\"hidden\" name=\"state\" value=\"" . CampUtils::arrayGet($_REQUEST, 'state', '') . "r\">\r\nPropose a new talk, starting at <select name=\"slot\">";
      foreach($Camp_DB->times as $intTimeID=>$strTime) {
        if($intTimeID>$now) {
          if($intTimeID==$_GET['slot']) {$selected='selected="selected"';} else {$selected='';}
          echo "<option value=\"$intTimeID\" $selected>{$Camp_DB->arrTimeEndPoints[$intTimeID]['s']}</option>";
        }
      }
      echo "</select>\r\n ";
      if(0==CampUtils::arrayGet($Camp_DB->config, 'sessions_fixed_to_one_slot', 0)) {
        echo "and with a length of \r\n<select name=\"length\">";
        if(isset($_GET['slot'])) {$left=count($Camp_DB->times)-($_GET['slot'])+1;} else {$left=count($Camp_DB->times);}
        for($l=1; $l<=$left; $l++) {echo "<option value=\"$l\">$l</option>";}
        echo "</select> \r\nslots. ";
      }
      echo "The talk will be about: \r\n<input type=\"text\" size=\"25\" name=\"title\" />\r\n<input type=\"submit\" value=\"Go\"/> <a href=\"$baseurl\">Or, click here if you changed your mind.</a>\r\n</form>";
      break;
    case "S":
      echo "\r\n<form method=\"post\" action=\"$baseurl\" class=\"DrawAttention\">\r\n<input type=\"hidden\" name=\"state\" value=\"" . CampUtils::arrayGet($_REQUEST, 'state', '') . "r\">\r\nPropose a new talk, starting at <select name=\"slot\">";
      foreach($Camp_DB->times as $intTimeID=>$strTime) {
        if($intTimeID>$now) {
          if($intTimeID==$_GET['slot']) {$selected='selected="selected"';} else {$selected='';}
          echo "<option value=\"$intTimeID\" $selected>{$Camp_DB->arrTimeEndPoints[$intTimeID]['s']}</option>";
        }
      }
      echo "</select>\r\n ";
      if(0==CampUtils::arrayGet($Camp_DB->config, 'sessions_fixed_to_one_slot', 0)) {
        echo "and with a length of \r\n<select name=\"length\">";
        if(isset($_GET['slot'])) {$left=count($Camp_DB->times)-($_GET['slot'])+1;} else {$left=count($Camp_DB->times);}
        for($l=1; $l<=$left; $l++) {echo "<option value=\"$l\">$l</option>";}
        echo "</select> \r\nslots. ";
      }
      echo "The talk will be";
      if(1==CampUtils::arrayGet($Camp_DB->config, 'dynamically_sort_whole_board_by_attendees', 0) or ("S"==CampUtils::arrayGet($_REQUEST, 'state', '') and !is_null(CampUtils::arrayGet($_REQUEST, 'room', null)))) {
        echo " in the non-dynamically allocated room \r\n<select name=\"room\">";
        foreach($Camp_DB->rooms as $intRoomID=>$arrRoom) {
          if(0==$arrRoom['boolIsDynamic'] or 0==CampUtils::arrayGet($Camp_DB->config, 'dynamically_sort_whole_board_by_attendees', 1)) {
            if($intRoomID==CampUtils::arrayGet($_REQUEST, 'room', '')) {$thisone='selected="selected"';} else {$thisone='';}
            echo "<option value=\"$intRoomID\" $thisone>{$arrRoom['strRoom']}</option>";
          }
        }
        echo "</select> \r\nand will be";
      }

      echo " about: \r\n<input type=\"text\" size=\"25\" name=\"title\" />\r\n<input type=\"submit\" value=\"Go\"/> <a href=\"$baseurl\">Or, click here if you changed your mind.</a>\r\n</form>";
      break;
    case "Pr":
      echo "<p class=\"RespondToAction\">Adding your talk</p>\r\n";
      if(1==CampUtils::arrayGet($Camp_DB->config, 'sessions_fixed_to_one_slot', 0)) {
        $Camp_DB->insertTalk(array($_REQUEST['slot'], $_REQUEST['title']), 0);
      } else {
        $Camp_DB->insertTalk(array($_REQUEST['slot'], $_REQUEST['length'], $_REQUEST['title']), 0);
      }
      break;
    case "Sr":
      echo "<p class=\"RespondToAction\">Adding your talk</p>\r\n";
      if(1==CampUtils::arrayGet($Camp_DB->config, 'sessions_fixed_to_one_slot', 0)) {
        $Camp_DB->insertStaticTalk($_REQUEST['slot'], $_REQUEST['room'], $_REQUEST['title'], 1, CampUtils::arrayGet($_SESSION, 'this_date', ''));
      } else {
        $Camp_DB->insertStaticTalk($_REQUEST['slot'], $_REQUEST['room'], $_REQUEST['title'], $_REQUEST['length'], CampUtils::arrayGet($_SESSION, 'this_date', ''));
      }
      break;
    case "C":
      echo "\r\n<form method=\"post\" action=\"$baseurl\" class=\"DrawAttention\">\r\n<input type=\"hidden\" name=\"state\" value=\"Ca\">\r\nCancel a talk with a talk ID of: <input type=\"text\" size=\"2\" name=\"talkid\" value=\"{$_GET['talkid']}\" />\r\n Because <input type=\"text\" size=\"25\" name=\"reason\" />\r\n<input type=\"submit\" value=\"Go\"/> <a href=\"$baseurl\">Or, click here if you changed your mind.</a>\r\n</form>";
      break;
    case "Ca":
      echo "<p class=\"RespondToAction\">Cancelling your talk ({$_REQUEST['talkid']})</p>\r\n";
      $Camp_DB->cancelTalk(array($_REQUEST['talkid'], $Camp_DB->arrTalks[$_REQUEST['talkid']]['intTimeID'], $Camp_DB->escape(htmlentities($_REQUEST['reason']))));
      break;
    case "E":
      echo "\r\n<form method=\"post\" action=\"$baseurl\" class=\"DrawAttention\">\r\n<input type=\"hidden\" name=\"state\" value=\"Ed\">\r\nRetitle a talk with a talk ID of: <input type=\"text\" size=\"2\" name=\"talkid\" value=\"{$_GET['talkid']}\" />\r\n With the new title <input type=\"text\" size=\"25\" name=\"ntitle\" />\r\n<input type=\"submit\" value=\"Go\"/> <a href=\"$baseurl\">Or, click here if you changed your mind.</a>\r\n</form>";
      break;
    case "Ed":
      echo "<p class=\"RespondToAction\">Retitling your talk ({$_REQUEST['talkid']})</p>\r\n";
      $Camp_DB->editTalk(array($_REQUEST['talkid'], $Camp_DB->arrTalks[$_REQUEST['talkid']]['intTimeID'], $Camp_DB->escape(htmlentities($_REQUEST['ntitle']) . ' ')));
      break;
    case "A":
    case "R":
      echo "<p class=\"RespondToAction\">Setting or removing attendance from the talk {$_REQUEST['talkid']}</p>\r\n";
      if($_REQUEST['state']=='A') {$Camp_DB->attendTalk($_REQUEST['talkid']);} else {$Camp_DB->declineTalk($_REQUEST['talkid']);}
      break;
  }
  $Camp_DB->refresh();
  echo "<div class=\"MenuBar\">\r\n<a href=\"$baseurl\" class=\"HideWithJS\">Reload this static page</a><span class=\"HideWithJS\"> |\r\n</span><a href=\"$baseurl?state=logout\">Log out</a> |\r\n <a href=\"$baseurl?state=O\">Add other access methods</a> |\r\n <a href=\"$baseurl?state=I\">Amend contact details</a> |\r\n <a href=\"{$baseurl}ical/\">iCal</a> ";
  if($Camp_DB->checkSupport()!=0 or $Camp_DB->checkAdmin()!=0) {echo "|\r\n <a href=\"{$baseurl}support/\">Provide support to attendees</a>";}
  if($Camp_DB->checkAdmin()!=0) {echo "|\r\n <a href=\"{$baseurl}admin.php\">Modify config values</a>";}
  echo "\r\n</div>\r\n";
  if($Camp_DB->checkAdmin()!=0) {
    echo "<form method=\"get\" action=\"$baseurl\" class=\"MenuBar\">Show a different date - leave blank for today: <input name=\"admin_use_date\" size=\"10\" value=\"" . CampUtils::arrayGet($_SESSION, 'today', '') . "\"> <input type=\"submit\" value=\"Go\"></form>";
  }
  echo '  <div id="mainbody" class="mainbody">' . $Camp_DB->getTimetableTemplate(FALSE, TRUE). '</div>
  <div id="sms_list" class="sms_list">' . $Camp_DB->getSmsTemplate() . '</div>
  <script type="text/javascript">update();</script>';
}
?>
</body>
</html>
