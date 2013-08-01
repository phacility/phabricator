ALTER TABLE {$NAMESPACE}_legalpad.legalpad_document
  ADD recentContributorPHIDs LONGTEXT NOT NULL COLLATE utf8_bin AFTER phid,
  ADD contributorCount INT UNSIGNED NOT NULL DEFAULT 0 AFTER phid,
  ADD title VARCHAR(255) NOT NULL COLLATE utf8_general_ci AFTER phid;

ALTER TABLE {$NAMESPACE}_legalpad.legalpad_document
  DROP KEY key_creator,
  ADD KEY key_creator (creatorPHID, dateModified);
