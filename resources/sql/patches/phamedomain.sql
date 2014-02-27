ALTER TABLE `{$NAMESPACE}_phame`.`phame_blog`
  ADD COLUMN `domain` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin
    AFTER `description`,
  ADD UNIQUE KEY (`domain`);
