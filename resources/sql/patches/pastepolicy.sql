ALTER TABLE `{$NAMESPACE}_pastebin`.`pastebin_paste`
  ADD `viewPolicy` varchar(64) COLLATE utf8_bin;

UPDATE `{$NAMESPACE}_pastebin`.`pastebin_paste` SET viewPolicy = 'users'
  WHERE viewPolicy IS NULL;
