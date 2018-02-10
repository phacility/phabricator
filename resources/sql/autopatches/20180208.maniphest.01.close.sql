ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  ADD closedEpoch INT UNSIGNED;

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  ADD closerPHID VARBINARY(64);
