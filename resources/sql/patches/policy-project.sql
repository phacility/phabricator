ALTER TABLE `{$NAMESPACE}_project`.`project`
  ADD `viewPolicy` varchar(64) COLLATE utf8_bin;

ALTER TABLE `{$NAMESPACE}_project`.`project`
  ADD `editPolicy` varchar(64) COLLATE utf8_bin;

ALTER TABLE `{$NAMESPACE}_project`.`project`
  ADD `joinPolicy` varchar(64) COLLATE utf8_bin;
