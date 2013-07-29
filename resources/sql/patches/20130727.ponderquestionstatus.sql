ALTER TABLE {$NAMESPACE}_ponder.ponder_question
  ADD COLUMN `status` INT(10) UNSIGNED NOT NULL AFTER `authorPHID`;

ALTER TABLE {$NAMESPACE}_ponder.ponder_question
  ADD INDEX `status` (`status`);
