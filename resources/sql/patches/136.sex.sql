ALTER TABLE `phabricator_user`.`user`
  ADD `sex` char(1) COLLATE utf8_bin AFTER `email`;
