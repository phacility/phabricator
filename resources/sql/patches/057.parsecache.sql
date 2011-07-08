TRUNCATE phabricator_differential.differential_changeset_parse_cache;

ALTER TABLE phabricator_differential.differential_changeset_parse_cache
  ADD dateCreated INT UNSIGNED NOT NULL;

ALTER TABLE phabricator_differential.differential_changeset_parse_cache
  ADD KEY (dateCreated);