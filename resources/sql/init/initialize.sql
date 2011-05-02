-- MySQL dump 10.13  Distrib 5.5.10, for osx10.6 (i386)
--
-- Host: localhost    Database: phabricator_conduit
-- ------------------------------------------------------
-- Server version	5.5.10

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `phabricator_conduit`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_conduit` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_conduit`;

--
-- Table structure for table `conduit_connectionlog`
--

DROP TABLE IF EXISTS `conduit_connectionlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conduit_connectionlog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client` varchar(255) DEFAULT NULL,
  `clientVersion` varchar(255) DEFAULT NULL,
  `clientDescription` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conduit_connectionlog`
--

LOCK TABLES `conduit_connectionlog` WRITE;
/*!40000 ALTER TABLE `conduit_connectionlog` DISABLE KEYS */;
/*!40000 ALTER TABLE `conduit_connectionlog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conduit_methodcalllog`
--

DROP TABLE IF EXISTS `conduit_methodcalllog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conduit_methodcalllog` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connectionID` bigint(20) unsigned DEFAULT NULL,
  `method` varchar(255) NOT NULL,
  `error` varchar(255) NOT NULL,
  `duration` bigint(20) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conduit_methodcalllog`
--

LOCK TABLES `conduit_methodcalllog` WRITE;
/*!40000 ALTER TABLE `conduit_methodcalllog` DISABLE KEYS */;
INSERT INTO `conduit_methodcalllog` VALUES (1,NULL,'daemon.log','',4569,1304349508,1304349508),(2,NULL,'daemon.log','',2335,1304349508,1304349508),(3,NULL,'daemon.log','',2463,1304349508,1304349508),(4,NULL,'daemon.log','',4507,1304349546,1304349546),(5,NULL,'daemon.log','',3366,1304349546,1304349546);
/*!40000 ALTER TABLE `conduit_methodcalllog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_daemon`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_daemon` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_daemon`;

--
-- Table structure for table `daemon_log`
--

DROP TABLE IF EXISTS `daemon_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daemon_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `daemon` varchar(255) NOT NULL,
  `host` varchar(255) NOT NULL,
  `pid` int(10) unsigned NOT NULL,
  `argv` varchar(512) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daemon_log`
--

LOCK TABLES `daemon_log` WRITE;
/*!40000 ALTER TABLE `daemon_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `daemon_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daemon_logevent`
--

DROP TABLE IF EXISTS `daemon_logevent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daemon_logevent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `logID` int(10) unsigned NOT NULL,
  `logType` varchar(4) NOT NULL,
  `message` longblob NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `logID` (`logID`,`epoch`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daemon_logevent`
--

LOCK TABLES `daemon_logevent` WRITE;
/*!40000 ALTER TABLE `daemon_logevent` DISABLE KEYS */;
INSERT INTO `daemon_logevent` VALUES (1,7,'INIT','',1304349508),(2,9,'INIT','',1304349508),(3,10,'INIT','',1304349508),(4,6,'INIT','',1304349546),(5,8,'INIT','',1304349546);
/*!40000 ALTER TABLE `daemon_logevent` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_differential`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_differential` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_differential`;

--
-- Table structure for table `differential_changeset`
--

DROP TABLE IF EXISTS `differential_changeset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `differential_changeset` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `diffID` int(10) unsigned NOT NULL,
  `oldFile` varchar(255) DEFAULT NULL,
  `fileName` varchar(255) NOT NULL,
  `awayPaths` longblob,
  `changeType` int(10) unsigned NOT NULL,
  `fileType` int(10) unsigned NOT NULL,
  `metadata` longblob,
  `oldProperties` longblob,
  `newProperties` longblob,
  `addLines` int(10) unsigned NOT NULL,
  `delLines` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `diffID` (`diffID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `differential_changeset`
--

LOCK TABLES `differential_changeset` WRITE;
/*!40000 ALTER TABLE `differential_changeset` DISABLE KEYS */;
/*!40000 ALTER TABLE `differential_changeset` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `differential_changeset_parse_cache`
--

DROP TABLE IF EXISTS `differential_changeset_parse_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `differential_changeset_parse_cache` (
  `id` int(10) unsigned NOT NULL,
  `cache` longblob NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `differential_changeset_parse_cache`
--

LOCK TABLES `differential_changeset_parse_cache` WRITE;
/*!40000 ALTER TABLE `differential_changeset_parse_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `differential_changeset_parse_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `differential_comment`
--

DROP TABLE IF EXISTS `differential_comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `differential_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `revisionID` int(10) unsigned NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `action` varchar(64) NOT NULL,
  `content` longblob NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `cache` longblob,
  PRIMARY KEY (`id`),
  KEY `revisionID` (`revisionID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `differential_comment`
--

LOCK TABLES `differential_comment` WRITE;
/*!40000 ALTER TABLE `differential_comment` DISABLE KEYS */;
/*!40000 ALTER TABLE `differential_comment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `differential_commit`
--

DROP TABLE IF EXISTS `differential_commit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `differential_commit` (
  `revisionID` int(10) unsigned NOT NULL,
  `commitPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  PRIMARY KEY (`revisionID`,`commitPHID`),
  UNIQUE KEY `commitPHID` (`commitPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `differential_commit`
--

LOCK TABLES `differential_commit` WRITE;
/*!40000 ALTER TABLE `differential_commit` DISABLE KEYS */;
/*!40000 ALTER TABLE `differential_commit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `differential_diff`
--

DROP TABLE IF EXISTS `differential_diff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `differential_diff` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `revisionID` int(10) unsigned DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `sourceMachine` varchar(255) DEFAULT NULL,
  `sourcePath` varchar(255) DEFAULT NULL,
  `sourceControlSystem` varchar(64) DEFAULT NULL,
  `sourceControlBaseRevision` varchar(255) DEFAULT NULL,
  `sourceControlPath` varchar(255) DEFAULT NULL,
  `lintStatus` int(10) unsigned NOT NULL,
  `unitStatus` int(10) unsigned NOT NULL,
  `lineCount` int(10) unsigned NOT NULL,
  `branch` varchar(255) DEFAULT NULL,
  `parentRevisionID` int(10) unsigned DEFAULT NULL,
  `arcanistProjectPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `creationMethod` varchar(255) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `repositoryUUID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `revisionID` (`revisionID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `differential_diff`
--

LOCK TABLES `differential_diff` WRITE;
/*!40000 ALTER TABLE `differential_diff` DISABLE KEYS */;
/*!40000 ALTER TABLE `differential_diff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `differential_diffproperty`
--

DROP TABLE IF EXISTS `differential_diffproperty`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `differential_diffproperty` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `diffID` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `data` longblob NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `diffID` (`diffID`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `differential_diffproperty`
--

LOCK TABLES `differential_diffproperty` WRITE;
/*!40000 ALTER TABLE `differential_diffproperty` DISABLE KEYS */;
/*!40000 ALTER TABLE `differential_diffproperty` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `differential_hunk`
--

DROP TABLE IF EXISTS `differential_hunk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `differential_hunk` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `changesetID` int(10) unsigned NOT NULL,
  `changes` longblob,
  `oldOffset` int(10) unsigned NOT NULL,
  `oldLen` int(10) unsigned NOT NULL,
  `newOffset` int(10) unsigned NOT NULL,
  `newLen` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `changesetID` (`changesetID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `differential_hunk`
--

LOCK TABLES `differential_hunk` WRITE;
/*!40000 ALTER TABLE `differential_hunk` DISABLE KEYS */;
/*!40000 ALTER TABLE `differential_hunk` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `differential_inlinecomment`
--

DROP TABLE IF EXISTS `differential_inlinecomment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `differential_inlinecomment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `revisionID` int(10) unsigned NOT NULL,
  `commentID` int(10) unsigned DEFAULT NULL,
  `authorPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `changesetID` int(10) unsigned NOT NULL,
  `isNewFile` tinyint(1) NOT NULL,
  `lineNumber` int(10) unsigned NOT NULL,
  `lineLength` int(10) unsigned NOT NULL,
  `content` longblob NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `cache` longblob,
  PRIMARY KEY (`id`),
  KEY `changesetID` (`changesetID`),
  KEY `commentID` (`commentID`),
  KEY `revisionID` (`revisionID`,`authorPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `differential_inlinecomment`
--

LOCK TABLES `differential_inlinecomment` WRITE;
/*!40000 ALTER TABLE `differential_inlinecomment` DISABLE KEYS */;
/*!40000 ALTER TABLE `differential_inlinecomment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `differential_relationship`
--

DROP TABLE IF EXISTS `differential_relationship`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `differential_relationship` (
  `revisionID` int(10) unsigned NOT NULL,
  `relation` varchar(4) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `reasonPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  PRIMARY KEY (`revisionID`,`relation`,`objectPHID`),
  KEY `objectPHID` (`objectPHID`,`relation`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `differential_relationship`
--

LOCK TABLES `differential_relationship` WRITE;
/*!40000 ALTER TABLE `differential_relationship` DISABLE KEYS */;
/*!40000 ALTER TABLE `differential_relationship` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `differential_revision`
--

DROP TABLE IF EXISTS `differential_revision`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `differential_revision` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `status` varchar(32) NOT NULL,
  `summary` longtext NOT NULL,
  `testPlan` text NOT NULL,
  `revertPlan` text NOT NULL,
  `blameRevision` varchar(255) NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `dateCommitted` int(10) unsigned DEFAULT NULL,
  `lineCount` int(10) unsigned DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `attached` longtext NOT NULL,
  `unsubscribed` longblob NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `authorPHID` (`authorPHID`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `differential_revision`
--

LOCK TABLES `differential_revision` WRITE;
/*!40000 ALTER TABLE `differential_revision` DISABLE KEYS */;
/*!40000 ALTER TABLE `differential_revision` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `differential_viewtime`
--

DROP TABLE IF EXISTS `differential_viewtime`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `differential_viewtime` (
  `viewerPHID` varchar(64) NOT NULL,
  `objectPHID` varchar(64) NOT NULL,
  `viewTime` int(10) unsigned NOT NULL,
  PRIMARY KEY (`viewerPHID`,`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `differential_viewtime`
--

LOCK TABLES `differential_viewtime` WRITE;
/*!40000 ALTER TABLE `differential_viewtime` DISABLE KEYS */;
/*!40000 ALTER TABLE `differential_viewtime` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_directory`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_directory` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_directory`;

--
-- Table structure for table `directory_category`
--

DROP TABLE IF EXISTS `directory_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `directory_category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `directory_category`
--

LOCK TABLES `directory_category` WRITE;
/*!40000 ALTER TABLE `directory_category` DISABLE KEYS */;
INSERT INTO `directory_category` VALUES (2,'Documentation',9000,1295318729,1304349639),(4,'Workflow',0,1295321164,1304349630),(5,'Utilities',100,1295321217,1295321217),(6,'Internals',2000,1295888559,1295888569);
/*!40000 ALTER TABLE `directory_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `directory_item`
--

DROP TABLE IF EXISTS `directory_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `directory_item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `href` varchar(255) NOT NULL,
  `categoryID` int(10) unsigned NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  `imagePHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=32 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `directory_item`
--

LOCK TABLES `directory_item` WRITE;
/*!40000 ALTER TABLE `directory_item` DISABLE KEYS */;
INSERT INTO `directory_item` VALUES (1,'Repositories','Configure tracked source code repositories.','/repository/',6,500,NULL,1304349659,1304349947),(5,'libphutil Docs','Soothing prose; seductive poetry.','http://phabricator.com/docs/libphutil/',2,300,'',1295312416,1304349695),(12,'Files','Blob store for Pokemon pictures.','/file/',5,100,'',1295321244,1304349844),(13,'Differential','Make code.','/differential/',4,100,'',1295321263,1304350150),(14,'PHID Manager','Manage PHIDs.','/phid/',6,400,'',1295762315,1304349943),(15,'People','User directory. Sort of a social utility.','/people/',5,400,'',1295830520,1304349833),(16,'Conduit Console','Web console for Conduit API.','/conduit/',6,100,'',1295888593,1304349910),(17,'MetaMTA','Yo dawg, we heard you like MTAs.','/mail/',6,300,'',1296006261,1304349936),(18,'XHProf','PHP profiling tool.','/xhprof/',6,600,NULL,1296684238,1304349951),(20,'Maniphest','Do meta-work instead of work.','/maniphest/',4,300,NULL,1297190663,1304349876),(21,'Arcanist Docs','Words have never been so finely crafted.','http://phabricator.com/docs/arcanist/',2,200,NULL,1304349712,1304349712),(22,'Phabricator Ducks','Oops, that should say \"Docs\".','http://phabricator.com/docs/phabricator/',2,100,NULL,1304349728,1304349728),(23,'Javelin Docs','O, what noble scribe hath penned these words?','http://phabricator.com/docs/javelin/',2,400,NULL,1304349746,1304349746),(24,'UI Examples','A gallery of modern art.','/uiexample/',2,500,NULL,1304349763,1304349769),(25,'Diffusion','Look at code.','/diffusion/',4,200,NULL,1304349788,1304349873),(26,'Herald','Watch for danger.','/herald/',4,400,NULL,1304349817,1304349817),(30,'Preferences','You are a snowflake princess.','/preferences/',5,600,NULL,1304350225,1304350307),(27,'Owners','Adopt today!','/owners/',5,500,NULL,1304349896,1304349896),(28,'Daemon Console','Offline process management.','/daemon/',6,200,NULL,1304349927,1304349927),(29,'XHPAST','XHP AST generator.','/xhpast/',6,700,NULL,1304350140,1304350140),(31,'Project','Group stuff into big piles.','/project/',5,500,NULL,1304350290,1304350299);
/*!40000 ALTER TABLE `directory_item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_draft`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_draft` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_draft`;

--
-- Table structure for table `draft`
--

DROP TABLE IF EXISTS `draft`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `draft` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `draftKey` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `draft` longblob NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `authorPHID` (`authorPHID`,`draftKey`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `draft`
--

LOCK TABLES `draft` WRITE;
/*!40000 ALTER TABLE `draft` DISABLE KEYS */;
/*!40000 ALTER TABLE `draft` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_file`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_file` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_file`;

--
-- Table structure for table `file`
--

DROP TABLE IF EXISTS `file`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `mimeType` varchar(255) DEFAULT NULL,
  `byteSize` bigint(20) unsigned NOT NULL,
  `storageEngine` varchar(32) NOT NULL,
  `storageFormat` varchar(32) NOT NULL,
  `storageHandle` varchar(255) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file`
--

LOCK TABLES `file` WRITE;
/*!40000 ALTER TABLE `file` DISABLE KEYS */;
INSERT INTO `file` VALUES (1,'PHID-FILE-4d61229816cfe6f2b2a3','avatar','image/png; charset=binary\n',959,'blob','raw','1',1304350408,1304350408);
/*!40000 ALTER TABLE `file` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_imagemacro`
--

DROP TABLE IF EXISTS `file_imagemacro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_imagemacro` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filePHID` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_imagemacro`
--

LOCK TABLES `file_imagemacro` WRITE;
/*!40000 ALTER TABLE `file_imagemacro` DISABLE KEYS */;
/*!40000 ALTER TABLE `file_imagemacro` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `file_storageblob`
--

DROP TABLE IF EXISTS `file_storageblob`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `file_storageblob` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longblob NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `file_storageblob`
--

LOCK TABLES `file_storageblob` WRITE;
/*!40000 ALTER TABLE `file_storageblob` DISABLE KEYS */;
INSERT INTO `file_storageblob` VALUES (1,'âPNG\r\n\Z\n\0\0\0\rIHDR\0\0\02\0\0\02\0\0\0ë]Ê\0\0\0tRNS\0}\0Ô\0C\"è§X\0\0tIDATxúÏôŸv¢@ÜÛ˛Ot«%öh4Q£à® .Äà,Œ$NNÜnYùú3Sß/∏àÙG’ﬂµtn~<Læ·∫˘Îˇ0Vπ1)5ÿÛs˘;`Å£÷Ê;œ•:[ÔÃ™O”ÔÄ≈nUc´x`g≤Îz‹BﬂU±∞7∂ƒ¬¸ƒ‘∆œCÒh;ùæ∞Qt«qππ|ﬂ‚Æ·-%\\AåìıB⁄…™©jÊF1ÃÉ≠já›˛†«…\\Êe&™ÖcAøÕﬁ¸ÈUhı‚ZÉ?NÉœ∞M¿¡ëb1u{∏ûá]=Œ\'≥w4›0©ÑQÎó˜êA,7äë<ﬁã®·X%e\nm»≠S∆ÀXƒt°§c:˘:sk>©¬.c·[!ﬁ‘X0^Pok„<±¿ƒŒ∂)„˜aªΩÖÛõ(á—∞¿‘{ìºåPßti€n„y_˛D,®°⁄ÊñìÍ√z#)æˆâXxéw^L∞©†0µÃXïgZvéX÷—AKÒ‚çU\nrUfQ}5TÃ˚&ß	#b·Ï‰MÂJ{Ö]ˆC˚—X8…›πõ=7DŸ¡≤—µ“Û>QÚwMN7èE`¡–å–õZÇ¿è¬⁄Î÷]∫QrÑ≤3ã¿B7ÜaÑG\"≤ﬂàﬂÅyU®%íÜUyúÊõQœ&¨4˙a§ik¿Æä`\nN\"èVáRπ…⁄jdm≥˛4«ıt√Bèèñ„\Z]íº¢±◊?üxT±ôÇπÌ\0Wù”!¯H°å∆Bñ|…ø&~6åìHç…ÇàèËæI≈1°¡ovÁâµÂc\ró1yûw±,í¥≈¢7r“at√pê≤Tá™óHÒº†ƒÈËiyÆŒù)Swz^9÷DŒø&…‹Àá\n´wfŸÎèa„¥~	ºÖwA˚ËCR3â+\rÉk“kàﬁ\nÔE6≤ûé)Ãô)Æì»ío∞hâñõ}ñŒøÖ:Q0ÚÒú‘Ó{√Ú¢†–L#˜pYêv´≠.mˆògxQE…ãÑC˛{K1œ‡o’;|xõçÏ%Æ5aµ°„∏é—á1µ1Sø“≈∫≠éÒÙë>ÀÁ‚c˘3A9B!Æñ#\"˛<°Ó˘REC´Èæ€TÕƒT‹>ªB≥ÄCˇñã&˘Ø{w›‰œx˜>\0\r1(+¡,˝æ\'œCÚW∫ˇéïq˝«J≤~\0\0ˇˇ\0UËÿ“H∫5Ú\0\0\0\0IENDÆB`Ç',1304350408,1304350408);
/*!40000 ALTER TABLE `file_storageblob` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_herald`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_herald` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_herald`;

--
-- Table structure for table `herald_action`
--

DROP TABLE IF EXISTS `herald_action`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `herald_action` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ruleID` int(10) unsigned NOT NULL,
  `action` varchar(255) NOT NULL,
  `target` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `herald_action`
--

LOCK TABLES `herald_action` WRITE;
/*!40000 ALTER TABLE `herald_action` DISABLE KEYS */;
/*!40000 ALTER TABLE `herald_action` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `herald_condition`
--

DROP TABLE IF EXISTS `herald_condition`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `herald_condition` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ruleID` int(10) unsigned NOT NULL,
  `fieldName` varchar(255) NOT NULL,
  `fieldCondition` varchar(255) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `herald_condition`
--

LOCK TABLES `herald_condition` WRITE;
/*!40000 ALTER TABLE `herald_condition` DISABLE KEYS */;
/*!40000 ALTER TABLE `herald_condition` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `herald_rule`
--

DROP TABLE IF EXISTS `herald_rule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `herald_rule` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `contentType` varchar(255) NOT NULL,
  `mustMatchAll` tinyint(1) NOT NULL,
  `configVersion` int(10) unsigned NOT NULL DEFAULT '1',
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `authorPHID` (`authorPHID`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `herald_rule`
--

LOCK TABLES `herald_rule` WRITE;
/*!40000 ALTER TABLE `herald_rule` DISABLE KEYS */;
/*!40000 ALTER TABLE `herald_rule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `herald_transcript`
--

DROP TABLE IF EXISTS `herald_transcript`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `herald_transcript` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `time` int(10) unsigned NOT NULL,
  `host` varchar(255) NOT NULL,
  `psth` varchar(255) NOT NULL,
  `duration` float NOT NULL,
  `objectPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `dryRun` tinyint(1) NOT NULL,
  `objectTranscript` longblob NOT NULL,
  `ruleTranscripts` longblob NOT NULL,
  `conditionTranscripts` longblob NOT NULL,
  `applyTranscripts` longblob NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  KEY `objectPHID` (`objectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `herald_transcript`
--

LOCK TABLES `herald_transcript` WRITE;
/*!40000 ALTER TABLE `herald_transcript` DISABLE KEYS */;
/*!40000 ALTER TABLE `herald_transcript` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_maniphest`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_maniphest` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_maniphest`;

--
-- Table structure for table `maniphest_task`
--

DROP TABLE IF EXISTS `maniphest_task`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maniphest_task` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `ownerPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `ccPHIDs` text,
  `attached` longtext NOT NULL,
  `status` int(10) unsigned NOT NULL,
  `priority` int(10) unsigned NOT NULL,
  `title` text NOT NULL,
  `description` longtext NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `projectPHIDs` longblob NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maniphest_task`
--

LOCK TABLES `maniphest_task` WRITE;
/*!40000 ALTER TABLE `maniphest_task` DISABLE KEYS */;
/*!40000 ALTER TABLE `maniphest_task` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maniphest_touch`
--

DROP TABLE IF EXISTS `maniphest_touch`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maniphest_touch` (
  `userPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `taskID` int(10) unsigned NOT NULL,
  `touchedAt` int(10) unsigned NOT NULL,
  PRIMARY KEY (`userPHID`,`taskID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maniphest_touch`
--

LOCK TABLES `maniphest_touch` WRITE;
/*!40000 ALTER TABLE `maniphest_touch` DISABLE KEYS */;
/*!40000 ALTER TABLE `maniphest_touch` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maniphest_transaction`
--

DROP TABLE IF EXISTS `maniphest_transaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maniphest_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `taskID` int(10) unsigned NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `transactionType` varchar(16) NOT NULL,
  `oldValue` longblob,
  `newValue` longblob,
  `comments` longblob,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `cache` longblob,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maniphest_transaction`
--

LOCK TABLES `maniphest_transaction` WRITE;
/*!40000 ALTER TABLE `maniphest_transaction` DISABLE KEYS */;
/*!40000 ALTER TABLE `maniphest_transaction` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_meta_data`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_meta_data` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_meta_data`;

--
-- Table structure for table `schema_version`
--

DROP TABLE IF EXISTS `schema_version`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schema_version` (
  `version` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schema_version`
--

LOCK TABLES `schema_version` WRITE;
/*!40000 ALTER TABLE `schema_version` DISABLE KEYS */;
INSERT INTO `schema_version` VALUES (33);
/*!40000 ALTER TABLE `schema_version` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_metamta`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_metamta` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_metamta`;

--
-- Table structure for table `metamta_mail`
--

DROP TABLE IF EXISTS `metamta_mail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `metamta_mail` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parameters` longblob NOT NULL,
  `status` varchar(255) NOT NULL,
  `message` text,
  `retryCount` int(10) unsigned NOT NULL,
  `nextRetry` int(10) unsigned NOT NULL,
  `relatedPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`,`nextRetry`),
  KEY `relatedPHID` (`relatedPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `metamta_mail`
--

LOCK TABLES `metamta_mail` WRITE;
/*!40000 ALTER TABLE `metamta_mail` DISABLE KEYS */;
/*!40000 ALTER TABLE `metamta_mail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `metamta_mailinglist`
--

DROP TABLE IF EXISTS `metamta_mailinglist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `metamta_mailinglist` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `uri` varchar(255) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `metamta_mailinglist`
--

LOCK TABLES `metamta_mailinglist` WRITE;
/*!40000 ALTER TABLE `metamta_mailinglist` DISABLE KEYS */;
/*!40000 ALTER TABLE `metamta_mailinglist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_owners`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_owners` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_owners`;

--
-- Table structure for table `owners_owner`
--

DROP TABLE IF EXISTS `owners_owner`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `owners_owner` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `packageID` int(10) unsigned NOT NULL,
  `userPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `packageID` (`packageID`,`userPHID`),
  KEY `userPHID` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `owners_owner`
--

LOCK TABLES `owners_owner` WRITE;
/*!40000 ALTER TABLE `owners_owner` DISABLE KEYS */;
/*!40000 ALTER TABLE `owners_owner` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `owners_package`
--

DROP TABLE IF EXISTS `owners_package`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `owners_package` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `primaryOwnerPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `owners_package`
--

LOCK TABLES `owners_package` WRITE;
/*!40000 ALTER TABLE `owners_package` DISABLE KEYS */;
/*!40000 ALTER TABLE `owners_package` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `owners_path`
--

DROP TABLE IF EXISTS `owners_path`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `owners_path` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `packageID` int(10) unsigned NOT NULL,
  `repositoryPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `path` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `packageID` (`packageID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `owners_path`
--

LOCK TABLES `owners_path` WRITE;
/*!40000 ALTER TABLE `owners_path` DISABLE KEYS */;
/*!40000 ALTER TABLE `owners_path` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_phid`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_phid` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_phid`;

--
-- Table structure for table `phid`
--

DROP TABLE IF EXISTS `phid`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `phid` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `phidType` varchar(4) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `parentPHID` varchar(64) DEFAULT NULL,
  `ownerPHID` varchar(64) DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `phid`
--

LOCK TABLES `phid` WRITE;
/*!40000 ALTER TABLE `phid` DISABLE KEYS */;
INSERT INTO `phid` VALUES (1,'PHID-FILE-4d61229816cfe6f2b2a3','FILE',NULL,NULL,1304350408,1304350408);
/*!40000 ALTER TABLE `phid` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_project`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_project` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_project`;

--
-- Table structure for table `project`
--

DROP TABLE IF EXISTS `project`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `authorPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project`
--

LOCK TABLES `project` WRITE;
/*!40000 ALTER TABLE `project` DISABLE KEYS */;
/*!40000 ALTER TABLE `project` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_affiliation`
--

DROP TABLE IF EXISTS `project_affiliation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_affiliation` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `projectPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `userPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `role` varchar(255) NOT NULL,
  `status` varchar(32) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projectPHID` (`projectPHID`,`userPHID`),
  KEY `userPHID` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_affiliation`
--

LOCK TABLES `project_affiliation` WRITE;
/*!40000 ALTER TABLE `project_affiliation` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_affiliation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_profile`
--

DROP TABLE IF EXISTS `project_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_profile` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `projectPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `blurb` longtext NOT NULL,
  `profileImagePHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projectPHID` (`projectPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_profile`
--

LOCK TABLES `project_profile` WRITE;
/*!40000 ALTER TABLE `project_profile` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_repository`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_repository` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_repository`;

--
-- Table structure for table `repository`
--

DROP TABLE IF EXISTS `repository`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repository` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `callsign` varchar(32) NOT NULL,
  `description` text,
  `versionControlSystem` varchar(32) NOT NULL,
  `details` longblob NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `uuid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `callsign` (`callsign`),
  UNIQUE KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repository`
--

LOCK TABLES `repository` WRITE;
/*!40000 ALTER TABLE `repository` DISABLE KEYS */;
/*!40000 ALTER TABLE `repository` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repository_arcanistproject`
--

DROP TABLE IF EXISTS `repository_arcanistproject`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repository_arcanistproject` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `repositoryID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repository_arcanistproject`
--

LOCK TABLES `repository_arcanistproject` WRITE;
/*!40000 ALTER TABLE `repository_arcanistproject` DISABLE KEYS */;
/*!40000 ALTER TABLE `repository_arcanistproject` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repository_badcommit`
--

DROP TABLE IF EXISTS `repository_badcommit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repository_badcommit` (
  `fullCommitName` varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `description` longblob NOT NULL,
  PRIMARY KEY (`fullCommitName`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repository_badcommit`
--

LOCK TABLES `repository_badcommit` WRITE;
/*!40000 ALTER TABLE `repository_badcommit` DISABLE KEYS */;
/*!40000 ALTER TABLE `repository_badcommit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repository_commit`
--

DROP TABLE IF EXISTS `repository_commit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repository_commit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryID` int(10) unsigned NOT NULL,
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `commitIdentifier` varchar(40) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `epoch` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phid` (`phid`),
  UNIQUE KEY `repositoryID` (`repositoryID`,`commitIdentifier`(16)),
  KEY `repositoryID_2` (`repositoryID`,`epoch`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repository_commit`
--

LOCK TABLES `repository_commit` WRITE;
/*!40000 ALTER TABLE `repository_commit` DISABLE KEYS */;
/*!40000 ALTER TABLE `repository_commit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repository_commitdata`
--

DROP TABLE IF EXISTS `repository_commitdata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repository_commitdata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `commitID` int(10) unsigned NOT NULL,
  `authorName` varchar(255) NOT NULL,
  `commitMessage` longblob NOT NULL,
  `commitDetails` longblob NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commitID` (`commitID`),
  KEY `authorName` (`authorName`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repository_commitdata`
--

LOCK TABLES `repository_commitdata` WRITE;
/*!40000 ALTER TABLE `repository_commitdata` DISABLE KEYS */;
/*!40000 ALTER TABLE `repository_commitdata` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repository_filesystem`
--

DROP TABLE IF EXISTS `repository_filesystem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repository_filesystem` (
  `repositoryID` int(10) unsigned NOT NULL,
  `parentID` int(10) unsigned NOT NULL,
  `svnCommit` int(10) unsigned NOT NULL,
  `pathID` int(10) unsigned NOT NULL,
  `existed` tinyint(1) NOT NULL,
  `fileType` int(10) unsigned NOT NULL,
  PRIMARY KEY (`repositoryID`,`parentID`,`pathID`,`svnCommit`),
  KEY `repositoryID` (`repositoryID`,`svnCommit`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repository_filesystem`
--

LOCK TABLES `repository_filesystem` WRITE;
/*!40000 ALTER TABLE `repository_filesystem` DISABLE KEYS */;
/*!40000 ALTER TABLE `repository_filesystem` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repository_githubnotification`
--

DROP TABLE IF EXISTS `repository_githubnotification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repository_githubnotification` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `repositoryPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `remoteAddress` varchar(32) NOT NULL,
  `payload` longblob NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `repositoryPHID` (`repositoryPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repository_githubnotification`
--

LOCK TABLES `repository_githubnotification` WRITE;
/*!40000 ALTER TABLE `repository_githubnotification` DISABLE KEYS */;
/*!40000 ALTER TABLE `repository_githubnotification` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repository_path`
--

DROP TABLE IF EXISTS `repository_path`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repository_path` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `path` varchar(512) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `path` (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repository_path`
--

LOCK TABLES `repository_path` WRITE;
/*!40000 ALTER TABLE `repository_path` DISABLE KEYS */;
/*!40000 ALTER TABLE `repository_path` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repository_pathchange`
--

DROP TABLE IF EXISTS `repository_pathchange`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repository_pathchange` (
  `repositoryID` int(10) unsigned NOT NULL,
  `pathID` int(10) unsigned NOT NULL,
  `commitID` int(10) unsigned NOT NULL,
  `targetPathID` int(10) unsigned DEFAULT NULL,
  `targetCommitID` int(10) unsigned DEFAULT NULL,
  `changeType` int(10) unsigned NOT NULL,
  `fileType` int(10) unsigned NOT NULL,
  `isDirect` tinyint(1) NOT NULL,
  `commitSequence` int(10) unsigned NOT NULL,
  PRIMARY KEY (`commitID`,`pathID`),
  KEY `repositoryID` (`repositoryID`,`pathID`,`commitSequence`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repository_pathchange`
--

LOCK TABLES `repository_pathchange` WRITE;
/*!40000 ALTER TABLE `repository_pathchange` DISABLE KEYS */;
/*!40000 ALTER TABLE `repository_pathchange` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repository_shortcut`
--

DROP TABLE IF EXISTS `repository_shortcut`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repository_shortcut` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `href` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `sequence` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repository_shortcut`
--

LOCK TABLES `repository_shortcut` WRITE;
/*!40000 ALTER TABLE `repository_shortcut` DISABLE KEYS */;
/*!40000 ALTER TABLE `repository_shortcut` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repository_summary`
--

DROP TABLE IF EXISTS `repository_summary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repository_summary` (
  `repositoryID` int(10) unsigned NOT NULL,
  `size` int(10) unsigned NOT NULL,
  `lastCommitID` int(10) unsigned NOT NULL,
  `epoch` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`repositoryID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repository_summary`
--

LOCK TABLES `repository_summary` WRITE;
/*!40000 ALTER TABLE `repository_summary` DISABLE KEYS */;
/*!40000 ALTER TABLE `repository_summary` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_search`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_search` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_search`;

--
-- Table structure for table `search_document`
--

DROP TABLE IF EXISTS `search_document`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `search_document` (
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `documentType` varchar(4) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `documentTitle` varchar(255) NOT NULL,
  `documentCreated` int(10) unsigned NOT NULL,
  `documentModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `search_document`
--

LOCK TABLES `search_document` WRITE;
/*!40000 ALTER TABLE `search_document` DISABLE KEYS */;
/*!40000 ALTER TABLE `search_document` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `search_documentfield`
--

DROP TABLE IF EXISTS `search_documentfield`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `search_documentfield` (
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `phidType` varchar(4) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `field` varchar(4) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `auxPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `corpus` text,
  KEY `phid` (`phid`),
  FULLTEXT KEY `corpus` (`corpus`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `search_documentfield`
--

LOCK TABLES `search_documentfield` WRITE;
/*!40000 ALTER TABLE `search_documentfield` DISABLE KEYS */;
/*!40000 ALTER TABLE `search_documentfield` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `search_documentrelationship`
--

DROP TABLE IF EXISTS `search_documentrelationship`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `search_documentrelationship` (
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `relatedPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `relation` varchar(4) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `relatedType` varchar(4) NOT NULL,
  `relatedTime` int(10) unsigned NOT NULL,
  KEY `phid` (`phid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `search_documentrelationship`
--

LOCK TABLES `search_documentrelationship` WRITE;
/*!40000 ALTER TABLE `search_documentrelationship` DISABLE KEYS */;
/*!40000 ALTER TABLE `search_documentrelationship` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `search_query`
--

DROP TABLE IF EXISTS `search_query`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `search_query` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `query` varchar(255) NOT NULL,
  `parameters` text NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `search_query`
--

LOCK TABLES `search_query` WRITE;
/*!40000 ALTER TABLE `search_query` DISABLE KEYS */;
/*!40000 ALTER TABLE `search_query` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_timeline`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_timeline` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_timeline`;

--
-- Table structure for table `timeline_cursor`
--

DROP TABLE IF EXISTS `timeline_cursor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timeline_cursor` (
  `name` varchar(255) NOT NULL,
  `position` int(10) unsigned NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timeline_cursor`
--

LOCK TABLES `timeline_cursor` WRITE;
/*!40000 ALTER TABLE `timeline_cursor` DISABLE KEYS */;
INSERT INTO `timeline_cursor` VALUES ('cmittask',0);
/*!40000 ALTER TABLE `timeline_cursor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `timeline_event`
--

DROP TABLE IF EXISTS `timeline_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timeline_event` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` char(4) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dataID` (`dataID`),
  KEY `type` (`type`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timeline_event`
--

LOCK TABLES `timeline_event` WRITE;
/*!40000 ALTER TABLE `timeline_event` DISABLE KEYS */;
/*!40000 ALTER TABLE `timeline_event` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `timeline_eventdata`
--

DROP TABLE IF EXISTS `timeline_eventdata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timeline_eventdata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `eventData` longblob NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timeline_eventdata`
--

LOCK TABLES `timeline_eventdata` WRITE;
/*!40000 ALTER TABLE `timeline_eventdata` DISABLE KEYS */;
/*!40000 ALTER TABLE `timeline_eventdata` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_user`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_user` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_user`;

--
-- Table structure for table `phabricator_session`
--

DROP TABLE IF EXISTS `phabricator_session`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `phabricator_session` (
  `userPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `type` varchar(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `sessionKey` varchar(40) NOT NULL,
  `sessionStart` int(10) unsigned NOT NULL,
  PRIMARY KEY (`userPHID`,`type`),
  UNIQUE KEY `sessionKey` (`sessionKey`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `phabricator_session`
--

LOCK TABLES `phabricator_session` WRITE;
/*!40000 ALTER TABLE `phabricator_session` DISABLE KEYS */;
/*!40000 ALTER TABLE `phabricator_session` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phid` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `userName` varchar(64) NOT NULL,
  `realName` varchar(128) NOT NULL,
  `email` varchar(255) NOT NULL,
  `passwordSalt` varchar(32) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `passwordHash` varchar(32) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `profileImagePHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `consoleEnabled` tinyint(1) NOT NULL,
  `consoleVisible` tinyint(1) NOT NULL,
  `consoleTab` varchar(64) NOT NULL,
  `conduitCertificate` varchar(255) NOT NULL,
  `isSystemAgent` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `userName` (`userName`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `phid` (`phid`),
  KEY `realName` (`realName`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_oauthinfo`
--

DROP TABLE IF EXISTS `user_oauthinfo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_oauthinfo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userID` int(10) unsigned NOT NULL,
  `oauthProvider` varchar(255) NOT NULL,
  `oauthUID` varchar(255) NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  `accountURI` varchar(255) DEFAULT NULL,
  `accountName` varchar(255) DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  `tokenExpires` int(10) unsigned DEFAULT NULL,
  `tokenScope` varchar(255) DEFAULT NULL,
  `tokenStatus` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userID` (`userID`,`oauthProvider`),
  UNIQUE KEY `oauthProvider` (`oauthProvider`,`oauthUID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_oauthinfo`
--

LOCK TABLES `user_oauthinfo` WRITE;
/*!40000 ALTER TABLE `user_oauthinfo` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_oauthinfo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_preferences`
--

DROP TABLE IF EXISTS `user_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_preferences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `preferences` longblob NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userPHID` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_preferences`
--

LOCK TABLES `user_preferences` WRITE;
/*!40000 ALTER TABLE `user_preferences` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_profile`
--

DROP TABLE IF EXISTS `user_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_profile` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  `title` varchar(255) NOT NULL,
  `blurb` text NOT NULL,
  `profileImagePHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `userPHID` (`userPHID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_profile`
--

LOCK TABLES `user_profile` WRITE;
/*!40000 ALTER TABLE `user_profile` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_profile` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_worker`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_worker` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_worker`;

--
-- Table structure for table `worker_task`
--

DROP TABLE IF EXISTS `worker_task`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `worker_task` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `taskClass` varchar(255) NOT NULL,
  `leaseOwner` varchar(255) DEFAULT NULL,
  `leaseExpires` int(10) unsigned DEFAULT NULL,
  `failureCount` int(10) unsigned NOT NULL,
  `dataID` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dataID` (`dataID`),
  KEY `taskClass` (`taskClass`),
  KEY `leaseExpires` (`leaseExpires`),
  KEY `leaseOwner` (`leaseOwner`(16))
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `worker_task`
--

LOCK TABLES `worker_task` WRITE;
/*!40000 ALTER TABLE `worker_task` DISABLE KEYS */;
/*!40000 ALTER TABLE `worker_task` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `worker_taskdata`
--

DROP TABLE IF EXISTS `worker_taskdata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `worker_taskdata` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` longblob NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `worker_taskdata`
--

LOCK TABLES `worker_taskdata` WRITE;
/*!40000 ALTER TABLE `worker_taskdata` DISABLE KEYS */;
/*!40000 ALTER TABLE `worker_taskdata` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Current Database: `phabricator_xhpastview`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `phabricator_xhpastview` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `phabricator_xhpastview`;

--
-- Table structure for table `xhpastview_parsetree`
--

DROP TABLE IF EXISTS `xhpastview_parsetree`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xhpastview_parsetree` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authorPHID` varchar(64) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
  `input` longblob NOT NULL,
  `stdout` longblob NOT NULL,
  `dateCreated` int(10) unsigned NOT NULL,
  `dateModified` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xhpastview_parsetree`
--

LOCK TABLES `xhpastview_parsetree` WRITE;
/*!40000 ALTER TABLE `xhpastview_parsetree` DISABLE KEYS */;
/*!40000 ALTER TABLE `xhpastview_parsetree` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2011-05-02  8:36:07
