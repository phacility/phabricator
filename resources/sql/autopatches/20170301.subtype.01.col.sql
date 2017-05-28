ALTER TABLE {$NAMESPACE}_search.search_editengineconfiguration
  ADD subtype VARCHAR(64) COLLATE {$COLLATE_TEXT} NOT NULL;
