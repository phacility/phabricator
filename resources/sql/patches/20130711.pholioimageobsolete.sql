ALTER TABLE {$NAMESPACE}_pholio.pholio_image
  ADD `isObsolete` TINYINT(1) NOT NULL DEFAULT '0';

ALTER TABLE {$NAMESPACE}_pholio.pholio_image
  DROP KEY `mockID`;

ALTER TABLE {$NAMESPACE}_pholio.pholio_image
  ADD KEY `mockID` (`mockID`, `isObsolete`, `sequence`);

ALTER TABLE {$NAMESPACE}_pholio.pholio_image
  ADD `phid` VARCHAR(64) NOT NULL COLLATE utf8_bin AFTER `id`;

ALTER TABLE {$NAMESPACE}_pholio.pholio_image
  CHANGE `mockID` `mockID` INT(10) UNSIGNED;
