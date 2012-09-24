ALTER TABLE `{$NAMESPACE}_draft`.`draft`
ADD `metadata` longtext NOT NULL DEFAULT '' AFTER `draft`;

UPDATE `{$NAMESPACE}_draft`.`draft` SET `metadata` = '[]';
