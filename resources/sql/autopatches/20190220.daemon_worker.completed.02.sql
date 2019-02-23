ALTER TABLE {$NAMESPACE}_worker.worker_activetask
  ADD dateCreated int unsigned NOT NULL,
  ADD dateModified int unsigned NOT NULL;
