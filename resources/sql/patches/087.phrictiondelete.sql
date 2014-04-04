ALTER TABLE {$NAMESPACE}_phriction.phriction_document
  ADD status INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE {$NAMESPACE}_phriction.phriction_content
  ADD changeType INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE {$NAMESPACE}_phriction.phriction_content
  ADD changeRef INT UNSIGNED DEFAULT NULL;
