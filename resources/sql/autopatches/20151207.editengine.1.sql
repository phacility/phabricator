ALTER TABLE {$NAMESPACE}_search.search_editengineconfiguration
  DROP editPolicy;

ALTER TABLE {$NAMESPACE}_search.search_editengineconfiguration
  ADD isEdit BOOL NOT NULL;

ALTER TABLE {$NAMESPACE}_search.search_editengineconfiguration
  ADD createOrder INT UNSIGNED NOT NULL;

ALTER TABLE {$NAMESPACE}_search.search_editengineconfiguration
  ADD editOrder INT UNSIGNED NOT NULL;
