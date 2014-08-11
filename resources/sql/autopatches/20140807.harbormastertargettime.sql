ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildtarget
  ADD dateStarted INT UNSIGNED NULL;

ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildtarget
  ADD dateCompleted INT UNSIGNED NULL;
