ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildable
  ADD isManualBuildable BOOL NOT NULL;

ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildable
  ADD KEY `key_manual` (isManualBuildable);
