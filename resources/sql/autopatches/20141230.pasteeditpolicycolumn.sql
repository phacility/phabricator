ALTER TABLE `{$NAMESPACE}_pastebin`.`pastebin_paste`
  ADD `editPolicy` VARBINARY(64) NOT NULL
  AFTER `viewPolicy`;
