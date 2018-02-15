ALTER TABLE {$NAMESPACE}_phriction.phriction_document
  CHANGE status status VARCHAR(32) NOT NULL COLLATE {$COLLATE_TEXT};
