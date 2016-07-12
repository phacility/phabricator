ALTER TABLE {$NAMESPACE}_worker.worker_activetask
  ADD objectPHID VARBINARY(64);

ALTER TABLE {$NAMESPACE}_worker.worker_archivetask
  ADD objectPHID VARBINARY(64);
