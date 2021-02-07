ALTER TABLE {$NAMESPACE}_worker.worker_activetask
  ADD containerPHID VARBINARY(64);

ALTER TABLE {$NAMESPACE}_worker.worker_archivetask
  ADD containerPHID VARBINARY(64);
