ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildplan
  ADD viewPolicy VARBINARY(64) NOT NULL;

ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildplan
  ADD editPolicy VARBINARY(64) NOT NULL;
