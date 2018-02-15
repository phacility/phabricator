ALTER TABLE {$NAMESPACE}_phriction.phriction_content
  CHANGE description description LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT};
