ALTER DATABASE `{$NAMESPACE}_audit` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_audit`.`audit_comment`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `targetPHID` varchar(64) CHARACTER SET binary,
  MODIFY `actorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `action` varchar(64) CHARACTER SET binary,
  MODIFY `content` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_audit`.`audit_comment`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `targetPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `actorPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `action` varchar(64) COLLATE utf8_general_ci NOT NULL,
  MODIFY `content` longtext COLLATE utf8_general_ci NOT NULL;



ALTER DATABASE `{$NAMESPACE}_chatlog` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_chatlog`.`chatlog_event`
  MODIFY `channel` varchar(64) CHARACTER SET binary,
  MODIFY `author` varchar(64) CHARACTER SET binary,
  MODIFY `type` varchar(4) CHARACTER SET binary,
  MODIFY `message` longtext CHARACTER SET binary,
  MODIFY `loggedByPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_chatlog`.`chatlog_event`
  COLLATE utf8_general_ci,
  MODIFY `channel` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `author` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `type` varchar(4) COLLATE utf8_general_ci NOT NULL,
  MODIFY `message` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `loggedByPHID` varchar(64) COLLATE utf8_bin NOT NULL;



ALTER DATABASE `{$NAMESPACE}_conduit` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_conduit`.`conduit_certificatetoken`
  MODIFY `userPHID` varchar(64) CHARACTER SET binary,
  MODIFY `token` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_conduit`.`conduit_certificatetoken`
  COLLATE utf8_general_ci,
  MODIFY `userPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `token` varchar(64) COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_conduit`.`conduit_connectionlog`
  MODIFY `client` varchar(255) CHARACTER SET binary,
  MODIFY `clientVersion` varchar(255) CHARACTER SET binary,
  MODIFY `clientDescription` varchar(255) CHARACTER SET binary,
  MODIFY `username` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_conduit`.`conduit_connectionlog`
  COLLATE utf8_general_ci,
  MODIFY `client` varchar(255) COLLATE utf8_general_ci,
  MODIFY `clientVersion` varchar(255) COLLATE utf8_general_ci,
  MODIFY `clientDescription` varchar(255) COLLATE utf8_general_ci,
  MODIFY `username` varchar(255) COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_conduit`.`conduit_methodcalllog`
  MODIFY `method` varchar(255) CHARACTER SET binary,
  MODIFY `error` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_conduit`.`conduit_methodcalllog`
  COLLATE utf8_general_ci,
  MODIFY `method` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `error` varchar(255) COLLATE utf8_general_ci NOT NULL;



ALTER DATABASE `{$NAMESPACE}_countdown` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_countdown`.`countdown_timer`
  MODIFY `title` varchar(255) CHARACTER SET binary,
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_countdown`.`countdown_timer`
  COLLATE utf8_general_ci,
  MODIFY `title` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin NOT NULL;



ALTER DATABASE `{$NAMESPACE}_daemon` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_daemon`.`daemon_log`
  MODIFY `daemon` varchar(255) CHARACTER SET binary,
  MODIFY `host` varchar(255) CHARACTER SET binary,
  MODIFY `argv` varchar(512) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_daemon`.`daemon_log`
  COLLATE utf8_general_ci,
  MODIFY `daemon` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `host` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `argv` varchar(512) COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_daemon`.`daemon_logevent`
  MODIFY `logType` varchar(4) CHARACTER SET binary,
  MODIFY `message` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_daemon`.`daemon_logevent`
  COLLATE utf8_general_ci,
  MODIFY `logType` varchar(4) COLLATE utf8_general_ci NOT NULL,
  MODIFY `message` longtext COLLATE utf8_bin NOT NULL;



ALTER DATABASE `{$NAMESPACE}_differential` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_differential`.`differential_affectedpath`
  COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_differential`.`differential_auxiliaryfield`
  MODIFY `revisionPHID` varchar(64) CHARACTER SET binary,
  MODIFY `name` varchar(32) CHARACTER SET binary,
  MODIFY `value` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_differential`.`differential_auxiliaryfield`
  COLLATE utf8_general_ci,
  MODIFY `revisionPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `name` varchar(32) COLLATE utf8_bin NOT NULL,
  MODIFY `value` longtext COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_differential`.`differential_changeset`
  MODIFY `oldFile` varchar(255) CHARACTER SET binary,
  MODIFY `filename` varchar(255) CHARACTER SET binary,
  MODIFY `awayPaths` longtext CHARACTER SET binary,
  MODIFY `metadata` longtext CHARACTER SET binary,
  MODIFY `oldProperties` longtext CHARACTER SET binary,
  MODIFY `newProperties` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_differential`.`differential_changeset`
  COLLATE utf8_general_ci,
  MODIFY `oldFile` varchar(255) COLLATE utf8_general_ci,
  MODIFY `filename` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `awayPaths` longtext COLLATE utf8_bin,
  MODIFY `metadata` longtext COLLATE utf8_bin,
  MODIFY `oldProperties` longtext COLLATE utf8_bin,
  MODIFY `newProperties` longtext COLLATE utf8_bin;

ALTER TABLE `{$NAMESPACE}_differential`.`differential_changeset_parse_cache`
  MODIFY `cache` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_differential`.`differential_changeset_parse_cache`
  COLLATE utf8_general_ci,
  MODIFY `cache` longtext COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_differential`.`differential_comment`
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `action` varchar(64) CHARACTER SET binary,
  MODIFY `content` longtext CHARACTER SET binary,
  MODIFY `cache` longtext CHARACTER SET binary,
  MODIFY `metadata` longtext CHARACTER SET binary,
  MODIFY `contentSource` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_differential`.`differential_comment`
  COLLATE utf8_general_ci,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `action` varchar(64) COLLATE utf8_general_ci NOT NULL,
  MODIFY `content` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `cache` longtext COLLATE utf8_bin,
  MODIFY `metadata` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `contentSource` varchar(255) COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_differential`.`differential_commit`
  MODIFY `commitPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_differential`.`differential_commit`
  COLLATE utf8_general_ci,
  MODIFY `commitPHID` varchar(64) COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_differential`.`differential_diff`
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `sourceMachine` varchar(255) CHARACTER SET binary,
  MODIFY `sourcePath` varchar(255) CHARACTER SET binary,
  MODIFY `sourceControlSystem` varchar(64) CHARACTER SET binary,
  MODIFY `sourceControlBaseRevision` varchar(255) CHARACTER SET binary,
  MODIFY `sourceControlPath` varchar(255) CHARACTER SET binary,
  MODIFY `branch` varchar(255) CHARACTER SET binary,
  MODIFY `arcanistProjectPHID` varchar(64) CHARACTER SET binary,
  MODIFY `creationMethod` varchar(255) CHARACTER SET binary,
  MODIFY `description` varchar(255) CHARACTER SET binary,
  MODIFY `repositoryUUID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_differential`.`differential_diff`
  COLLATE utf8_general_ci,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin,
  MODIFY `sourceMachine` varchar(255) COLLATE utf8_general_ci,
  MODIFY `sourcePath` varchar(255) COLLATE utf8_general_ci,
  MODIFY `sourceControlSystem` varchar(64) COLLATE utf8_general_ci,
  MODIFY `sourceControlBaseRevision` varchar(255) COLLATE utf8_general_ci,
  MODIFY `sourceControlPath` varchar(255) COLLATE utf8_general_ci,
  MODIFY `branch` varchar(255) COLLATE utf8_general_ci,
  MODIFY `arcanistProjectPHID` varchar(64) COLLATE utf8_bin,
  MODIFY `creationMethod` varchar(255) COLLATE utf8_general_ci,
  MODIFY `description` varchar(255) COLLATE utf8_general_ci,
  MODIFY `repositoryUUID` varchar(64) COLLATE utf8_bin;

ALTER TABLE `{$NAMESPACE}_differential`.`differential_diffproperty`
  MODIFY `name` varchar(255) CHARACTER SET binary,
  MODIFY `data` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_differential`.`differential_diffproperty`
  COLLATE utf8_general_ci,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `data` longtext COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_differential`.`differential_hunk`
  MODIFY `changes` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_differential`.`differential_hunk`
  COLLATE utf8_general_ci,
  MODIFY `changes` longtext COLLATE utf8_bin;

ALTER TABLE `{$NAMESPACE}_differential`.`differential_inlinecomment`
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `content` longtext CHARACTER SET binary,
  MODIFY `cache` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_differential`.`differential_inlinecomment`
  COLLATE utf8_general_ci,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `content` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `cache` longtext COLLATE utf8_bin;

ALTER TABLE `{$NAMESPACE}_differential`.`differential_relationship`
  MODIFY `relation` varchar(4) CHARACTER SET binary,
  MODIFY `objectPHID` varchar(64) CHARACTER SET binary,
  MODIFY `reasonPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_differential`.`differential_relationship`
  COLLATE utf8_general_ci,
  MODIFY `relation` varchar(4) COLLATE utf8_bin NOT NULL,
  MODIFY `objectPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `reasonPHID` varchar(64) COLLATE utf8_bin;

ALTER TABLE `{$NAMESPACE}_differential`.`differential_revision`
  MODIFY `title` varchar(255) CHARACTER SET binary,
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `status` varchar(32) CHARACTER SET binary,
  MODIFY `summary` longtext CHARACTER SET binary,
  MODIFY `testPlan` text CHARACTER SET binary,
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `lastReviewerPHID` varchar(64) CHARACTER SET binary,
  MODIFY `attached` longtext CHARACTER SET binary,
  MODIFY `unsubscribed` longtext CHARACTER SET binary,
  MODIFY `mailKey` varchar(40) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_differential`.`differential_revision`
  COLLATE utf8_general_ci,
  MODIFY `title` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `status` varchar(32) COLLATE utf8_general_ci NOT NULL,
  MODIFY `summary` longtext COLLATE utf8_general_ci NOT NULL,
  MODIFY `testPlan` text COLLATE utf8_general_ci NOT NULL,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin,
  MODIFY `lastReviewerPHID` varchar(64) COLLATE utf8_bin,
  MODIFY `attached` longtext COLLATE utf8_general_ci NOT NULL,
  MODIFY `unsubscribed` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `mailKey` varchar(40) COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_differential`.`differential_revisionhash`
  MODIFY `type` char(4) CHARACTER SET binary,
  MODIFY `hash` varchar(40) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_differential`.`differential_revisionhash`
  COLLATE utf8_general_ci,
  MODIFY `type` char(4) COLLATE utf8_bin NOT NULL,
  MODIFY `hash` varchar(40) COLLATE utf8_bin NOT NULL;



ALTER DATABASE `{$NAMESPACE}_draft` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_draft`.`draft`
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `draftKey` varchar(64) CHARACTER SET binary,
  MODIFY `draft` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_draft`.`draft`
  COLLATE utf8_general_ci,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `draftKey` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `draft` longtext COLLATE utf8_bin NOT NULL;



ALTER DATABASE `{$NAMESPACE}_drydock` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_drydock`.`drydock_lease`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `ownerPHID` varchar(64) CHARACTER SET binary,
  MODIFY `attributes` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_drydock`.`drydock_lease`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `ownerPHID` varchar(64) COLLATE utf8_bin,
  MODIFY `attributes` longtext COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_drydock`.`drydock_resource`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `name` varchar(255) CHARACTER SET binary,
  MODIFY `ownerPHID` varchar(64) CHARACTER SET binary,
  MODIFY `blueprintClass` varchar(255) CHARACTER SET binary,
  MODIFY `type` varchar(64) CHARACTER SET binary,
  MODIFY `attributes` longtext CHARACTER SET binary,
  MODIFY `capabilities` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_drydock`.`drydock_resource`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `ownerPHID` varchar(64) COLLATE utf8_bin,
  MODIFY `blueprintClass` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `type` varchar(64) COLLATE utf8_general_ci NOT NULL,
  MODIFY `attributes` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `capabilities` longtext COLLATE utf8_bin NOT NULL;



ALTER DATABASE `{$NAMESPACE}_feed` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_feed`.`feed_storydata`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `storyType` varchar(64) CHARACTER SET binary,
  MODIFY `storyData` longtext CHARACTER SET binary,
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_feed`.`feed_storydata`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `storyType` varchar(64) COLLATE utf8_general_ci NOT NULL,
  MODIFY `storyData` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_feed`.`feed_storyreference`
  MODIFY `objectPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_feed`.`feed_storyreference`
  COLLATE utf8_general_ci,
  MODIFY `objectPHID` varchar(64) COLLATE utf8_bin NOT NULL;



ALTER DATABASE `{$NAMESPACE}_file` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_file`.`file`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `name` varchar(255) CHARACTER SET binary,
  MODIFY `mimeType` varchar(255) CHARACTER SET binary,
  MODIFY `storageEngine` varchar(32) CHARACTER SET binary,
  MODIFY `storageFormat` varchar(32) CHARACTER SET binary,
  MODIFY `storageHandle` varchar(255) CHARACTER SET binary,
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `secretKey` varchar(20) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_file`.`file`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci,
  MODIFY `mimeType` varchar(255) COLLATE utf8_general_ci,
  MODIFY `storageEngine` varchar(32) COLLATE utf8_general_ci NOT NULL,
  MODIFY `storageFormat` varchar(32) COLLATE utf8_general_ci NOT NULL,
  MODIFY `storageHandle` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin,
  MODIFY `secretKey` varchar(20) COLLATE utf8_bin;

ALTER TABLE `{$NAMESPACE}_file`.`file_imagemacro`
  MODIFY `filePHID` varchar(64) CHARACTER SET binary,
  MODIFY `name` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_file`.`file_imagemacro`
  COLLATE utf8_general_ci,
  MODIFY `filePHID` varchar(64) COLLATE utf8_general_ci NOT NULL,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_file`.`file_proxyimage`
  MODIFY `uri` varchar(255) CHARACTER SET binary,
  MODIFY `filePHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_file`.`file_proxyimage`
  COLLATE utf8_general_ci,
  MODIFY `uri` varchar(255) COLLATE utf8_bin NOT NULL,
  MODIFY `filePHID` varchar(64) COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_file`.`file_storageblob`
  COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_file`.`file_transformedfile`
  MODIFY `originalPHID` varchar(64) CHARACTER SET binary,
  MODIFY `transform` varchar(255) CHARACTER SET binary,
  MODIFY `transformedPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_file`.`file_transformedfile`
  COLLATE utf8_general_ci,
  MODIFY `originalPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `transform` varchar(255) COLLATE utf8_bin NOT NULL,
  MODIFY `transformedPHID` varchar(64) COLLATE utf8_bin NOT NULL;



ALTER DATABASE `{$NAMESPACE}_herald` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_herald`.`herald_action`
  MODIFY `action` varchar(255) CHARACTER SET binary,
  MODIFY `target` text CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_herald`.`herald_action`
  COLLATE utf8_general_ci,
  MODIFY `action` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `target` text COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_herald`.`herald_condition`
  MODIFY `fieldName` varchar(255) CHARACTER SET binary,
  MODIFY `fieldCondition` varchar(255) CHARACTER SET binary,
  MODIFY `value` text CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_herald`.`herald_condition`
  COLLATE utf8_general_ci,
  MODIFY `fieldName` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `fieldCondition` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `value` text COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_herald`.`herald_rule`
  MODIFY `name` varchar(255) CHARACTER SET binary,
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `contentType` varchar(255) CHARACTER SET binary,
  MODIFY `ruleType` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_herald`.`herald_rule`
  COLLATE utf8_general_ci,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `contentType` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `ruleType` varchar(255) COLLATE utf8_general_ci NOT NULL DEFAULT 'global';

ALTER TABLE `{$NAMESPACE}_herald`.`herald_ruleapplied`
  MODIFY `phid` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_herald`.`herald_ruleapplied`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_herald`.`herald_ruleedit`
  MODIFY `editorPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_herald`.`herald_ruleedit`
  COLLATE utf8_general_ci,
  MODIFY `editorPHID` varchar(64) COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_herald`.`herald_savedheader`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `header` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_herald`.`herald_savedheader`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `header` varchar(255) COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_herald`.`herald_transcript`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `host` varchar(255) CHARACTER SET binary,
  MODIFY `psth` varchar(255) CHARACTER SET binary,
  MODIFY `objectPHID` varchar(64) CHARACTER SET binary,
  MODIFY `objectTranscript` longtext CHARACTER SET binary,
  MODIFY `ruleTranscripts` longtext CHARACTER SET binary,
  MODIFY `conditionTranscripts` longtext CHARACTER SET binary,
  MODIFY `applyTranscripts` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_herald`.`herald_transcript`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `host` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `psth` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `objectPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `objectTranscript` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `ruleTranscripts` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `conditionTranscripts` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `applyTranscripts` longtext COLLATE utf8_bin NOT NULL;



ALTER DATABASE `{$NAMESPACE}_maniphest` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_maniphest`.`maniphest_task`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `ownerPHID` varchar(64) CHARACTER SET binary,
  MODIFY `ccPHIDs` text CHARACTER SET binary,
  MODIFY `attached` longtext CHARACTER SET binary,
  MODIFY `title` text CHARACTER SET binary,
  MODIFY `description` longtext CHARACTER SET binary,
  MODIFY `projectPHIDs` longtext CHARACTER SET binary,
  MODIFY `mailKey` varchar(40) CHARACTER SET binary,
  MODIFY `ownerOrdering` varchar(64) CHARACTER SET binary,
  MODIFY `originalEmailSource` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_maniphest`.`maniphest_task`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `ownerPHID` varchar(64) COLLATE utf8_bin,
  MODIFY `ccPHIDs` text COLLATE utf8_general_ci,
  MODIFY `attached` longtext COLLATE utf8_general_ci NOT NULL,
  MODIFY `title` text COLLATE utf8_general_ci NOT NULL,
  MODIFY `description` longtext COLLATE utf8_general_ci NOT NULL,
  MODIFY `projectPHIDs` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `mailKey` varchar(40) COLLATE utf8_bin NOT NULL,
  MODIFY `ownerOrdering` varchar(64) COLLATE utf8_general_ci,
  MODIFY `originalEmailSource` varchar(255) COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_maniphest`.`maniphest_taskauxiliarystorage`
  MODIFY `taskPHID` varchar(64) CHARACTER SET binary,
  MODIFY `name` varchar(255) CHARACTER SET binary,
  MODIFY `value` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_maniphest`.`maniphest_taskauxiliarystorage`
  COLLATE utf8_general_ci,
  MODIFY `taskPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `value` varchar(255) COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_maniphest`.`maniphest_taskproject`
  MODIFY `taskPHID` varchar(64) CHARACTER SET binary,
  MODIFY `projectPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_maniphest`.`maniphest_taskproject`
  COLLATE utf8_general_ci,
  MODIFY `taskPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `projectPHID` varchar(64) COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_maniphest`.`maniphest_tasksubscriber`
  MODIFY `taskPHID` varchar(64) CHARACTER SET binary,
  MODIFY `subscriberPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_maniphest`.`maniphest_tasksubscriber`
  COLLATE utf8_general_ci,
  MODIFY `taskPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `subscriberPHID` varchar(64) COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_maniphest`.`maniphest_touch`
  MODIFY `userPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_maniphest`.`maniphest_touch`
  COLLATE utf8_general_ci,
  MODIFY `userPHID` varchar(64) COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_maniphest`.`maniphest_transaction`
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `transactionType` varchar(16) CHARACTER SET binary,
  MODIFY `oldValue` longtext CHARACTER SET binary,
  MODIFY `newValue` longtext CHARACTER SET binary,
  MODIFY `comments` longtext CHARACTER SET binary,
  MODIFY `cache` longtext CHARACTER SET binary,
  MODIFY `metadata` longtext CHARACTER SET binary,
  MODIFY `contentSource` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_maniphest`.`maniphest_transaction`
  COLLATE utf8_general_ci,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `transactionType` varchar(16) COLLATE utf8_general_ci NOT NULL,
  MODIFY `oldValue` longtext COLLATE utf8_bin,
  MODIFY `newValue` longtext COLLATE utf8_bin,
  MODIFY `comments` longtext COLLATE utf8_bin,
  MODIFY `cache` longtext COLLATE utf8_bin,
  MODIFY `metadata` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `contentSource` varchar(255) COLLATE utf8_general_ci;



ALTER DATABASE `{$NAMESPACE}_meta_data` COLLATE utf8_general_ci;



ALTER DATABASE `{$NAMESPACE}_metamta` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_metamta`.`metamta_mail`
  MODIFY `parameters` longtext CHARACTER SET binary,
  MODIFY `status` varchar(255) CHARACTER SET binary,
  MODIFY `message` text CHARACTER SET binary,
  MODIFY `relatedPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_metamta`.`metamta_mail`
  COLLATE utf8_general_ci,
  MODIFY `parameters` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `status` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `message` text COLLATE utf8_general_ci,
  MODIFY `relatedPHID` varchar(64) COLLATE utf8_bin;

ALTER TABLE `{$NAMESPACE}_metamta`.`metamta_mailinglist`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `name` varchar(255) CHARACTER SET binary,
  MODIFY `email` varchar(255) CHARACTER SET binary,
  MODIFY `uri` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_metamta`.`metamta_mailinglist`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `email` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `uri` varchar(255) COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_metamta`.`metamta_receivedmail`
  MODIFY `headers` longtext CHARACTER SET binary,
  MODIFY `bodies` longtext CHARACTER SET binary,
  MODIFY `attachments` longtext CHARACTER SET binary,
  MODIFY `relatedPHID` varchar(64) CHARACTER SET binary,
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `message` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_metamta`.`metamta_receivedmail`
  COLLATE utf8_general_ci,
  MODIFY `headers` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `bodies` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `attachments` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `relatedPHID` varchar(64) COLLATE utf8_bin,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin,
  MODIFY `message` longtext COLLATE utf8_bin;



ALTER DATABASE `{$NAMESPACE}_oauth_server` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_oauth_server`.`oauth_server_oauthclientauthorization`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `userPHID` varchar(64) CHARACTER SET binary,
  MODIFY `clientPHID` varchar(64) CHARACTER SET binary,
  MODIFY `scope` text CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_oauth_server`.`oauth_server_oauthclientauthorization`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `userPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `clientPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `scope` text COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_oauth_server`.`oauth_server_oauthserveraccesstoken`
  MODIFY `token` varchar(32) CHARACTER SET binary,
  MODIFY `userPHID` varchar(64) CHARACTER SET binary,
  MODIFY `clientPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_oauth_server`.`oauth_server_oauthserveraccesstoken`
  COLLATE utf8_general_ci,
  MODIFY `token` varchar(32) COLLATE utf8_general_ci NOT NULL,
  MODIFY `userPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `clientPHID` varchar(64) COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_oauth_server`.`oauth_server_oauthserverauthorizationcode`
  MODIFY `code` varchar(32) CHARACTER SET binary,
  MODIFY `clientPHID` varchar(64) CHARACTER SET binary,
  MODIFY `clientSecret` varchar(32) CHARACTER SET binary,
  MODIFY `userPHID` varchar(64) CHARACTER SET binary,
  MODIFY `redirectURI` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_oauth_server`.`oauth_server_oauthserverauthorizationcode`
  COLLATE utf8_general_ci,
  MODIFY `code` varchar(32) COLLATE utf8_general_ci NOT NULL,
  MODIFY `clientPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `clientSecret` varchar(32) COLLATE utf8_general_ci NOT NULL,
  MODIFY `userPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `redirectURI` varchar(255) COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_oauth_server`.`oauth_server_oauthserverclient`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `name` varchar(255) CHARACTER SET binary,
  MODIFY `secret` varchar(32) CHARACTER SET binary,
  MODIFY `redirectURI` varchar(255) CHARACTER SET binary,
  MODIFY `creatorPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_oauth_server`.`oauth_server_oauthserverclient`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `secret` varchar(32) COLLATE utf8_general_ci NOT NULL,
  MODIFY `redirectURI` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `creatorPHID` varchar(64) COLLATE utf8_bin NOT NULL;



ALTER DATABASE `{$NAMESPACE}_owners` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_owners`.`owners_owner`
  MODIFY `userPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_owners`.`owners_owner`
  COLLATE utf8_general_ci,
  MODIFY `userPHID` varchar(64) COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_owners`.`owners_package`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `name` varchar(255) CHARACTER SET binary,
  MODIFY `description` text CHARACTER SET binary,
  MODIFY `primaryOwnerPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_owners`.`owners_package`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `description` text COLLATE utf8_general_ci NOT NULL,
  MODIFY `primaryOwnerPHID` varchar(64) COLLATE utf8_bin;

ALTER TABLE `{$NAMESPACE}_owners`.`owners_path`
  MODIFY `repositoryPHID` varchar(64) CHARACTER SET binary,
  MODIFY `path` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_owners`.`owners_path`
  COLLATE utf8_general_ci,
  MODIFY `repositoryPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `path` varchar(255) COLLATE utf8_general_ci NOT NULL;



ALTER DATABASE `{$NAMESPACE}_pastebin` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_pastebin`.`pastebin_paste`
  MODIFY `title` varchar(255) CHARACTER SET binary,
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `filePHID` varchar(64) CHARACTER SET binary,
  MODIFY `language` varchar(64) CHARACTER SET binary,
  MODIFY `parentPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_pastebin`.`pastebin_paste`
  COLLATE utf8_general_ci,
  MODIFY `title` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `filePHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `language` varchar(64) COLLATE utf8_general_ci NOT NULL,
  MODIFY `parentPHID` varchar(64) COLLATE utf8_bin;



ALTER DATABASE `{$NAMESPACE}_phriction` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_phriction`.`phriction_content`
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `title` varchar(512) CHARACTER SET binary,
  MODIFY `slug` varchar(512) CHARACTER SET binary,
  MODIFY `content` longtext CHARACTER SET binary,
  MODIFY `description` varchar(512) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_phriction`.`phriction_content`
  COLLATE utf8_general_ci,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `title` varchar(512) COLLATE utf8_general_ci NOT NULL,
  MODIFY `slug` varchar(512) COLLATE utf8_general_ci NOT NULL,
  MODIFY `content` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `description` varchar(512) COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_phriction`.`phriction_document`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `slug` varchar(128) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_phriction`.`phriction_document`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `slug` varchar(128) COLLATE utf8_general_ci NOT NULL;



ALTER DATABASE `{$NAMESPACE}_project` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_project`.`project`
  MODIFY `name` varchar(255) CHARACTER SET binary,
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `status` varchar(32) CHARACTER SET binary,
  MODIFY `subprojectPHIDs` longtext CHARACTER SET binary,
  MODIFY `phrictionSlug` varchar(128) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_project`.`project`
  COLLATE utf8_general_ci,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `status` varchar(32) COLLATE utf8_general_ci NOT NULL,
  MODIFY `subprojectPHIDs` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `phrictionSlug` varchar(128) COLLATE utf8_bin;

ALTER TABLE `{$NAMESPACE}_project`.`project_affiliation`
  MODIFY `projectPHID` varchar(64) CHARACTER SET binary,
  MODIFY `userPHID` varchar(64) CHARACTER SET binary,
  MODIFY `role` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_project`.`project_affiliation`
  COLLATE utf8_general_ci,
  MODIFY `projectPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `userPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `role` varchar(255) COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_project`.`project_profile`
  MODIFY `projectPHID` varchar(64) CHARACTER SET binary,
  MODIFY `blurb` longtext CHARACTER SET binary,
  MODIFY `profileImagePHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_project`.`project_profile`
  COLLATE utf8_general_ci,
  MODIFY `projectPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `blurb` longtext COLLATE utf8_general_ci NOT NULL,
  MODIFY `profileImagePHID` varchar(64) COLLATE utf8_bin;

ALTER TABLE `{$NAMESPACE}_project`.`project_subproject`
  MODIFY `projectPHID` varchar(64) CHARACTER SET binary,
  MODIFY `subprojectPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_project`.`project_subproject`
  COLLATE utf8_general_ci,
  MODIFY `projectPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `subprojectPHID` varchar(64) COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_project`.`project_transaction`
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `transactionType` varchar(32) CHARACTER SET binary,
  MODIFY `oldValue` longtext CHARACTER SET binary,
  MODIFY `newValue` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_project`.`project_transaction`
  COLLATE utf8_general_ci,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `transactionType` varchar(32) COLLATE utf8_general_ci NOT NULL,
  MODIFY `oldValue` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `newValue` longtext COLLATE utf8_bin NOT NULL;



ALTER DATABASE `{$NAMESPACE}_repository` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_repository`.`repository`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `name` varchar(255) CHARACTER SET binary,
  MODIFY `callsign` varchar(32) CHARACTER SET binary,
  MODIFY `versionControlSystem` varchar(32) CHARACTER SET binary,
  MODIFY `details` longtext CHARACTER SET binary,
  MODIFY `uuid` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_repository`.`repository`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `callsign` varchar(32) COLLATE utf8_general_ci NOT NULL,
  MODIFY `versionControlSystem` varchar(32) COLLATE utf8_general_ci NOT NULL,
  MODIFY `details` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `uuid` varchar(64) COLLATE utf8_bin;

ALTER TABLE `{$NAMESPACE}_repository`.`repository_arcanistproject`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `name` varchar(255) CHARACTER SET binary,
  MODIFY `symbolIndexLanguages` longtext CHARACTER SET binary,
  MODIFY `symbolIndexProjects` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_repository`.`repository_arcanistproject`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `symbolIndexLanguages` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `symbolIndexProjects` longtext COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_repository`.`repository_auditrequest`
  MODIFY `auditorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `commitPHID` varchar(64) CHARACTER SET binary,
  MODIFY `auditStatus` varchar(64) CHARACTER SET binary,
  MODIFY `auditReasons` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_repository`.`repository_auditrequest`
  COLLATE utf8_general_ci,
  MODIFY `auditorPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `commitPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `auditStatus` varchar(64) COLLATE utf8_general_ci NOT NULL,
  MODIFY `auditReasons` longtext COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_repository`.`repository_badcommit`
  MODIFY `fullCommitName` varchar(255) CHARACTER SET binary,
  MODIFY `description` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_repository`.`repository_badcommit`
  COLLATE utf8_general_ci,
  MODIFY `fullCommitName` varchar(255) COLLATE utf8_bin NOT NULL,
  MODIFY `description` longtext COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_repository`.`repository_commit`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `commitIdentifier` varchar(40) CHARACTER SET binary,
  MODIFY `mailKey` varchar(20) CHARACTER SET binary,
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_repository`.`repository_commit`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `commitIdentifier` varchar(40) COLLATE utf8_bin NOT NULL,
  MODIFY `mailKey` varchar(20) COLLATE utf8_general_ci NOT NULL,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin;

ALTER TABLE `{$NAMESPACE}_repository`.`repository_commitdata`
  MODIFY `authorName` varchar(255) CHARACTER SET binary,
  MODIFY `commitMessage` longtext CHARACTER SET binary,
  MODIFY `commitDetails` longtext CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_repository`.`repository_commitdata`
  COLLATE utf8_general_ci,
  MODIFY `authorName` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `commitMessage` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `commitDetails` longtext COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_repository`.`repository_filesystem`
  COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_repository`.`repository_path`
  MODIFY `path` varchar(512) CHARACTER SET binary,
  MODIFY `pathHash` varchar(32) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_repository`.`repository_path`
  COLLATE utf8_general_ci,
  MODIFY `path` varchar(512) COLLATE utf8_bin NOT NULL,
  MODIFY `pathHash` varchar(32) COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_repository`.`repository_pathchange`
  COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_repository`.`repository_shortcut`
  MODIFY `name` varchar(255) CHARACTER SET binary,
  MODIFY `href` varchar(255) CHARACTER SET binary,
  MODIFY `description` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_repository`.`repository_shortcut`
  COLLATE utf8_general_ci,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `href` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `description` varchar(255) COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_repository`.`repository_summary`
  COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_repository`.`repository_symbol`
  MODIFY `symbolName` varchar(128) CHARACTER SET binary,
  MODIFY `symbolType` varchar(12) CHARACTER SET binary,
  MODIFY `symbolLanguage` varchar(32) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_repository`.`repository_symbol`
  COLLATE utf8_general_ci,
  MODIFY `symbolName` varchar(128) COLLATE utf8_general_ci NOT NULL,
  MODIFY `symbolType` varchar(12) COLLATE utf8_bin NOT NULL,
  MODIFY `symbolLanguage` varchar(32) COLLATE utf8_bin NOT NULL;



ALTER DATABASE `{$NAMESPACE}_search` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_search`.`search_document`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `documentType` varchar(4) CHARACTER SET binary,
  MODIFY `documentTitle` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_search`.`search_document`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `documentType` varchar(4) COLLATE utf8_bin NOT NULL,
  MODIFY `documentTitle` varchar(255) COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_search`.`search_documentfield`
  DROP INDEX corpus,
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `phidType` varchar(4) CHARACTER SET binary,
  MODIFY `field` varchar(4) CHARACTER SET binary,
  MODIFY `auxPHID` varchar(64) CHARACTER SET binary,
  MODIFY `corpus` text CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_search`.`search_documentfield`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `phidType` varchar(4) COLLATE utf8_bin NOT NULL,
  MODIFY `field` varchar(4) COLLATE utf8_bin NOT NULL,
  MODIFY `auxPHID` varchar(64) COLLATE utf8_bin,
  MODIFY `corpus` text COLLATE utf8_general_ci,
  ADD FULLTEXT (corpus);

ALTER TABLE `{$NAMESPACE}_search`.`search_documentrelationship`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `relatedPHID` varchar(64) CHARACTER SET binary,
  MODIFY `relation` varchar(4) CHARACTER SET binary,
  MODIFY `relatedType` varchar(4) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_search`.`search_documentrelationship`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `relatedPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `relation` varchar(4) COLLATE utf8_bin NOT NULL,
  MODIFY `relatedType` varchar(4) COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_search`.`search_query`
  MODIFY `query` varchar(255) CHARACTER SET binary,
  MODIFY `parameters` text CHARACTER SET binary,
  MODIFY `queryKey` varchar(12) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_search`.`search_query`
  COLLATE utf8_general_ci,
  MODIFY `query` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `parameters` text COLLATE utf8_general_ci NOT NULL,
  MODIFY `queryKey` varchar(12) COLLATE utf8_general_ci NOT NULL;



ALTER DATABASE `{$NAMESPACE}_slowvote` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_slowvote`.`slowvote_choice`
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_slowvote`.`slowvote_choice`
  COLLATE utf8_general_ci,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_slowvote`.`slowvote_comment`
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_slowvote`.`slowvote_comment`
  COLLATE utf8_general_ci,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `commentText` longtext COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_slowvote`.`slowvote_option`
  MODIFY `name` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_slowvote`.`slowvote_option`
  COLLATE utf8_general_ci,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_slowvote`.`slowvote_poll`
  MODIFY `question` varchar(255) CHARACTER SET binary,
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_slowvote`.`slowvote_poll`
  COLLATE utf8_general_ci,
  MODIFY `question` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin NOT NULL;



ALTER DATABASE `{$NAMESPACE}_timeline` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_timeline`.`timeline_cursor`
  MODIFY `name` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_timeline`.`timeline_cursor`
  COLLATE utf8_general_ci,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_timeline`.`timeline_event`
  MODIFY `type` char(4) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_timeline`.`timeline_event`
  COLLATE utf8_general_ci,
  MODIFY `type` char(4) COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_timeline`.`timeline_eventdata`
  COLLATE utf8_general_ci,
  MODIFY `eventData` longtext COLLATE utf8_bin NOT NULL;



ALTER DATABASE `{$NAMESPACE}_user` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_user`.`phabricator_session`
  MODIFY `userPHID` varchar(64) CHARACTER SET binary,
  MODIFY `type` varchar(32) CHARACTER SET binary,
  MODIFY `sessionKey` varchar(40) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_user`.`phabricator_session`
  COLLATE utf8_general_ci,
  MODIFY `userPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `type` varchar(32) COLLATE utf8_bin NOT NULL,
  MODIFY `sessionKey` varchar(40) COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_user`.`user`
  MODIFY `phid` varchar(64) CHARACTER SET binary,
  MODIFY `userName` varchar(64) CHARACTER SET binary,
  MODIFY `realName` varchar(128) CHARACTER SET binary,
  MODIFY `email` varchar(255) CHARACTER SET binary,
  MODIFY `passwordSalt` varchar(32) CHARACTER SET binary,
  MODIFY `passwordHash` varchar(32) CHARACTER SET binary,
  MODIFY `profileImagePHID` varchar(64) CHARACTER SET binary,
  MODIFY `consoleTab` varchar(64) CHARACTER SET binary,
  MODIFY `conduitCertificate` varchar(255) CHARACTER SET binary,
  MODIFY `timezoneIdentifier` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_user`.`user`
  COLLATE utf8_general_ci,
  MODIFY `phid` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `userName` varchar(64) COLLATE utf8_general_ci NOT NULL,
  MODIFY `realName` varchar(128) COLLATE utf8_general_ci NOT NULL,
  MODIFY `email` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `passwordSalt` varchar(32) COLLATE utf8_bin,
  MODIFY `passwordHash` varchar(32) COLLATE utf8_bin,
  MODIFY `profileImagePHID` varchar(64) COLLATE utf8_bin,
  MODIFY `consoleTab` varchar(64) COLLATE utf8_general_ci NOT NULL,
  MODIFY `conduitCertificate` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `timezoneIdentifier` varchar(255) COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_user`.`user_log`
  MODIFY `actorPHID` varchar(64) CHARACTER SET binary,
  MODIFY `userPHID` varchar(64) CHARACTER SET binary,
  MODIFY `action` varchar(64) CHARACTER SET binary,
  MODIFY `remoteAddr` varchar(16) CHARACTER SET binary,
  MODIFY `session` varchar(40) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_user`.`user_log`
  COLLATE utf8_general_ci,
  MODIFY `actorPHID` varchar(64) COLLATE utf8_bin,
  MODIFY `userPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `action` varchar(64) COLLATE utf8_general_ci NOT NULL,
  MODIFY `oldValue` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `newValue` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `details` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `remoteAddr` varchar(16) COLLATE utf8_general_ci NOT NULL,
  MODIFY `session` varchar(40) COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_user`.`user_nametoken`
  MODIFY `token` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_user`.`user_nametoken`
  COLLATE utf8_general_ci,
  MODIFY `token` varchar(255) COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `{$NAMESPACE}_user`.`user_oauthinfo`
  MODIFY `oauthProvider` varchar(255) CHARACTER SET binary,
  MODIFY `oauthUID` varchar(255) CHARACTER SET binary,
  MODIFY `accountURI` varchar(255) CHARACTER SET binary,
  MODIFY `accountName` varchar(255) CHARACTER SET binary,
  MODIFY `token` varchar(255) CHARACTER SET binary,
  MODIFY `tokenScope` varchar(255) CHARACTER SET binary,
  MODIFY `tokenStatus` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_user`.`user_oauthinfo`
  COLLATE utf8_general_ci,
  MODIFY `oauthProvider` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `oauthUID` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `accountURI` varchar(255) COLLATE utf8_general_ci,
  MODIFY `accountName` varchar(255) COLLATE utf8_general_ci,
  MODIFY `token` varchar(255) COLLATE utf8_general_ci,
  MODIFY `tokenScope` varchar(255) COLLATE utf8_general_ci,
  MODIFY `tokenStatus` varchar(255) COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_user`.`user_preferences`
  MODIFY `userPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_user`.`user_preferences`
  COLLATE utf8_general_ci,
  MODIFY `userPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `preferences` longtext COLLATE utf8_bin NOT NULL;

ALTER TABLE `{$NAMESPACE}_user`.`user_profile`
  MODIFY `userPHID` varchar(64) CHARACTER SET binary,
  MODIFY `title` varchar(255) CHARACTER SET binary,
  MODIFY `blurb` text CHARACTER SET binary,
  MODIFY `profileImagePHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_user`.`user_profile`
  COLLATE utf8_general_ci,
  MODIFY `userPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `title` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `blurb` text COLLATE utf8_general_ci NOT NULL,
  MODIFY `profileImagePHID` varchar(64) COLLATE utf8_bin;

ALTER TABLE `{$NAMESPACE}_user`.`user_sshkey`
  MODIFY `userPHID` varchar(64) CHARACTER SET binary,
  MODIFY `name` varchar(255) CHARACTER SET binary,
  MODIFY `keyType` varchar(255) CHARACTER SET binary,
  MODIFY `keyBody` text CHARACTER SET binary,
  MODIFY `keyHash` varchar(32) CHARACTER SET binary,
  MODIFY `keyComment` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_user`.`user_sshkey`
  COLLATE utf8_general_ci,
  MODIFY `userPHID` varchar(64) COLLATE utf8_bin NOT NULL,
  MODIFY `name` varchar(255) COLLATE utf8_general_ci,
  MODIFY `keyType` varchar(255) COLLATE utf8_general_ci,
  MODIFY `keyBody` text COLLATE utf8_bin,
  MODIFY `keyHash` varchar(32) COLLATE utf8_bin NOT NULL,
  MODIFY `keyComment` varchar(255) COLLATE utf8_general_ci;



ALTER DATABASE `{$NAMESPACE}_worker` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_worker`.`worker_task`
  MODIFY `taskClass` varchar(255) CHARACTER SET binary,
  MODIFY `leaseOwner` varchar(255) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_worker`.`worker_task`
  COLLATE utf8_general_ci,
  MODIFY `taskClass` varchar(255) COLLATE utf8_general_ci NOT NULL,
  MODIFY `leaseOwner` varchar(255) COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_worker`.`worker_taskdata`
  COLLATE utf8_general_ci,
  MODIFY `data` longtext COLLATE utf8_bin NOT NULL;



ALTER DATABASE `{$NAMESPACE}_xhpastview` COLLATE utf8_general_ci;

ALTER TABLE `{$NAMESPACE}_xhpastview`.`xhpastview_parsetree`
  MODIFY `authorPHID` varchar(64) CHARACTER SET binary;
ALTER TABLE `{$NAMESPACE}_xhpastview`.`xhpastview_parsetree`
  COLLATE utf8_general_ci,
  MODIFY `authorPHID` varchar(64) COLLATE utf8_bin,
  MODIFY `input` longtext COLLATE utf8_bin NOT NULL,
  MODIFY `stdout` longtext COLLATE utf8_bin NOT NULL;
