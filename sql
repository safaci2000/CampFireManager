DROP TABLE IF EXISTS `account_microblog`;
CREATE TABLE `account_microblog` (
  `intMbID` int(11) NOT NULL AUTO_INCREMENT,
  `strAccount` varchar(255) NOT NULL,
  `strApiBase` varchar(255) NOT NULL,
  `strPassword` varchar(255) NOT NULL,
  `intLastMessage` bigint(20) NOT NULL,
  PRIMARY KEY (`intMbID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `account_phones`;
CREATE TABLE `account_phones` (
  `intPhoneID` int(11) NOT NULL AUTO_INCREMENT,
  `strPhone` varchar(255) NOT NULL,
  `strNumber` varchar(255) NOT NULL,
  `intSignal` int(11) NOT NULL,
  `strGammuRef` varchar(255) NOT NULL,
  PRIMARY KEY (`intPhoneID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `agent`;
CREATE TABLE `agent` (
  `intAgentID` int(11) NOT NULL AUTO_INCREMENT,
  `strAgent` varchar(255) NOT NULL,
  `isEnabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`intAgentID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `attendees`;
CREATE TABLE `attendees` (
  `intAttendID` int(11) NOT NULL AUTO_INCREMENT,
  `intTalkID` int(11) NOT NULL,
  `intPersonID` int(11) NOT NULL,
  PRIMARY KEY (`intAttendID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `changes`;
CREATE TABLE `changes` (
  `intChangeID` int(255) NOT NULL AUTO_INCREMENT,
  `intTalkID` int(11) NOT NULL,
  `intPersonID` int(11) NOT NULL,
  PRIMARY KEY (`intChangeID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `config`;
CREATE TABLE `config` (
  `strConfig` varchar(255) NOT NULL,
  `strValue` text NOT NULL,
  PRIMARY KEY (`strConfig`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `config` (`strConfig`, `strValue`) VALUES ('FixRoomOffset', '-15 minutes'),
                                                      ('event_title', 'A CampFireManager Event'),
                                                      ('support_regen', '1'),
                                                      ('admin_regen', '1'),
                                                      ('require_contact_details', '1'),
                                                      ('dynamically_sort_whole_board_by_attendees', '1'),
                                                      ('sessions_fixed_to_one_slot', '0'),
                                                      ('website', 'http://localhost/');

DROP TABLE IF EXISTS `people`;
CREATE TABLE `people` (
  `intPersonID` int(11) NOT NULL AUTO_INCREMENT,
  `strPhoneNumber` varchar(20) NOT NULL,
  `strName` varchar(255) NOT NULL,
  `strContactInfo` varchar(255) NOT NULL,
  `strDefaultReply` varchar(100) NOT NULL,
  `strOpenID` text NOT NULL,
  `strMicroBlog` varchar(255) NOT NULL,
  `strAuthString` varchar(25) NOT NULL,
  `boolIsAdmin` tinyint(1) NOT NULL DEFAULT '0',
  `boolIsSupport` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`intPersonID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `room_directions`;
CREATE TABLE `room_directions` (
  `intDirectionID` int(11) NOT NULL AUTO_INCREMENT,
  `intScreenID` int(11) NOT NULL,
  `intDestRoomID` int(11) NOT NULL,
  `intDirectionURDL` enum('U','R','D','L','UR','DR','DL','UL') NOT NULL,
  PRIMARY KEY (`intDirectionID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `intRoomID` int(11) NOT NULL AUTO_INCREMENT,
  `strRoom` varchar(255) NOT NULL,
  `intCapacity` int(11) NOT NULL,
  `boolIsDynamic` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`intRoomID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `screens`;
CREATE TABLE `screens` (
  `intScreenID` int(11) NOT NULL AUTO_INCREMENT,
  `strHostname` varchar(255) NOT NULL,
  PRIMARY KEY (`intScreenID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `sms_screen`;
CREATE TABLE `sms_screen` (
  `intUpdateID` int(11) NOT NULL AUTO_INCREMENT,
  `intPersonID` int(11) NOT NULL,
  `strMessage` text NOT NULL,
  `datInsert` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`intUpdateID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `talks`;
CREATE TABLE `talks` (
  `intTalkID` int(11) NOT NULL AUTO_INCREMENT,
  `intTimeID` int(11) NOT NULL,
  `datTalk` date NOT NULL,
  `intRoomID` int(11) NOT NULL,
  `intPersonID` int(11) NOT NULL,
  `strTalkTitle` text NOT NULL,
  `boolFixed` tinyint(1) NOT NULL,
  `intAttendees` int(11) NOT NULL,
  `intLength` tinyint(1) NOT NULL,
  PRIMARY KEY (`intTalkID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `times`;
CREATE TABLE `times` (
  `intTimeID` int(11) NOT NULL AUTO_INCREMENT,
  `strTime` varchar(100) NOT NULL,
  `intTimeType` int(1) NOT NULL,
  PRIMARY KEY (`intTimeID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `timetypes`;
CREATE TABLE `timetypes` (
  `intTimeTypeID` int(11) NOT NULL AUTO_INCREMENT,
  `strTimeType` varchar(255) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`intTimeTypeID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

INSERT INTO `timetypes` (`strTimeType`) VALUES ('Lunchtime'), 
                                               ('Breakfast'), 
                                               ('Dinner'), 
                                               ('Tea Break'), 
                                               ('Elevensies'), 
                                               ('Supper'), 
                                               ('Opening & Intro'), 
                                               ('Closing & Goodbye'), 
                                               ('Tidy up');
