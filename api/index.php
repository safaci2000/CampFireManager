<?php
require_once("../db.php");
require_once("{$base_dir}REST.php");

$rp=$_DATA->getRequestPath();
switch(strtolower(trim($rp[0]))) {
  case 'echo':
    sendArray($_DATA->getRequestPath(), 200, $_DATA);
    break;
  case 'echologin':
    requireAuth($_DATA);
    sendArray(array('Auth'=>$_DATA->getAuth(), 'ReqPath'=>$_DATA->getRequestPath()), 200, $_DATA);
    break;
  case 'timetable':
    $return=array();
    foreach($Camp_DB->arrTalkSlots as $intTimeID=>$arrTalkData) {
      $return[$intTimeID]=array();
      foreach($arrTalkData as $intRoomID=>$intTalkID) {
        if($intTalkID>0) {
          $return[$intTimeID][$intRoomID]=$Camp_DB->arrTalks[$intTalkID];
          $return[$intTimeID][$intRoomID]['strNonTalk']='';
        } elseif($intTalkID==0) {
          $return[$intTimeID][$intRoomID]=array('strNonTalk'=>'Empty');
        } else {
          $return[$intTimeID][$intRoomID]=array('strNonTalk'=>$Camp_DB->timetypes[-$intTalkID]);
        }
      }
    }
    sendArray($return, 200, $_DATA);
    break;
}

?>
