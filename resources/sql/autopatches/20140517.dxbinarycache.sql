TRUNCATE {$NAMESPACE}_differential.differential_changeset_parse_cache;

ALTER TABLE {$NAMESPACE}_differential.differential_changeset_parse_cache
  CHANGE cache cache LONGTEXT COLLATE latin1_bin NOT NULL;
