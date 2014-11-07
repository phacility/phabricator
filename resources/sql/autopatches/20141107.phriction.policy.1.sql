ALTER TABLE {$NAMESPACE}_phriction.phriction_document
  ADD viewPolicy VARBINARY(64) NOT NULL;

ALTER TABLE {$NAMESPACE}_phriction.phriction_document
  ADD editPolicy VARBINARY(64) NOT NULL;
