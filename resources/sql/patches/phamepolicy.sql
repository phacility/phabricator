ALTER TABLE `{$NAMESPACE}_phame`.`phame_blog`
  ADD `viewPolicy` varchar(64) COLLATE utf8_bin;

UPDATE `{$NAMESPACE}_phame`.`phame_blog` SET viewPolicy = 'users'
  WHERE viewPolicy IS NULL;

ALTER TABLE `{$NAMESPACE}_phame`.`phame_blog`
  ADD `editPolicy` varchar(64) COLLATE utf8_bin;

UPDATE `{$NAMESPACE}_phame`.`phame_blog` SET editPolicy = 'users'
  WHERE editPolicy IS NULL;

ALTER TABLE `{$NAMESPACE}_phame`.`phame_blog`
  ADD `joinPolicy` varchar(64) COLLATE utf8_bin;

UPDATE `{$NAMESPACE}_phame`.`phame_blog` SET joinPolicy = 'users'
  WHERE joinPolicy IS NULL;
