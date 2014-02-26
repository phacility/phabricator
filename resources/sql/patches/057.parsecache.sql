TRUNCATE {$NAMESPACE}_differential.differential_changeset_parse_cache;

ALTER TABLE {$NAMESPACE}_differential.differential_changeset_parse_cache
  ADD dateCreated INT UNSIGNED NOT NULL;

ALTER TABLE {$NAMESPACE}_differential.differential_changeset_parse_cache
  ADD KEY (dateCreated);
