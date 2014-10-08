ALTER TABLE {$NAMESPACE}_worker.worker_activetask
  ADD COLUMN priority int unsigned NOT NULL;

ALTER TABLE {$NAMESPACE}_worker.worker_activetask
  ADD KEY (leaseOwner, priority, id);

ALTER TABLE {$NAMESPACE}_worker.worker_archivetask
  ADD COLUMN priority int unsigned NOT NULL;

ALTER TABLE {$NAMESPACE}_worker.worker_archivetask
  ADD KEY (leaseOwner, priority, id);
