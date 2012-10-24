ALTER TABLE `{$NAMESPACE}_draft`.`draft`
ADD `metadata` longtext AFTER `draft`;

UPDATE `{$NAMESPACE}_draft`.`draft` SET `metadata` = '[]';
