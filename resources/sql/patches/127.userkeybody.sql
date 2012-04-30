ALTER TABLE `{$NAMESPACE}_user`.`user_sshkey`
  MODIFY `keyBody` text COLLATE utf8_bin;
