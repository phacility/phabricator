ALTER TABLE {$NAMESPACE}_search.search_namedquery
  ADD isBuiltin BOOL NOT NULL DEFAULT 0;

ALTER TABLE {$NAMESPACE}_search.search_namedquery
  ADD isDisabled BOOL NOT NULL DEFAULT 0;

ALTER TABLE {$NAMESPACE}_search.search_namedquery
  ADD sequence INT UNSIGNED NOT NULL DEFAULT 0;
