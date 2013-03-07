ALTER TABLE `{$NAMESPACE}_repository`.`repository_lintmessage`
  ADD authorPHID varchar(64) COLLATE utf8_bin AFTER line,
  ADD INDEX key_author (authorPHID);
