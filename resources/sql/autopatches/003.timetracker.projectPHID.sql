CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_timetracker` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_timetracker`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

ALTER TABLE {$NAMESPACE}_timetracker.timetracker_trackedtime
  ADD IF NOT EXISTS `projectPHID` varbinary(64) NOT NULL;