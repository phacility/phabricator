RENAME TABLE `{$NAMESPACE}_user`.`externalaccount`
  TO `{$NAMESPACE}_user`.`user_externalaccount`;

ALTER TABLE `{$NAMESPACE}_user`.`user_externalaccount`
  ADD `dateCreated` INT UNSIGNED NOT NULL,
  ADD `dateModified` INT UNSIGNED NOT NULL;
