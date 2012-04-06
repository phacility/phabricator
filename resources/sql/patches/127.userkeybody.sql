ALTER TABLE `phabricator_user`.`user_sshkey`
  MODIFY `keyBody` text COLLATE utf8_bin;
