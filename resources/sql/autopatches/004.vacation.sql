CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$NAMESPACE}_vacation` /*!40100 DEFAULT CHARACTER SET {$CHARSET} COLLATE {$COLLATE_TEXT} */;

USE `{$NAMESPACE}_vacation`;

 SET NAMES utf8 ;

 SET character_set_client = {$CHARSET} ;

CREATE TABLE IF NOT EXISTS `vacation_day` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userID` int(10) unsigned NOT NULL,
  `numMinutes` int(10) NOT NULL,
  `dateWhenTrackedFor` int(12) NOT NULL,
  `realDateWhenTracked` int(12) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};