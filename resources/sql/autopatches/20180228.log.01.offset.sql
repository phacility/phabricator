ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildlogchunk
  ADD headOffset BIGINT UNSIGNED NOT NULL;

ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildlogchunk
  ADD tailOffset BIGINT UNSIGNED NOT NULL;
