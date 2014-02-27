ALTER TABLE {$NAMESPACE}_worker.worker_activetask
  ADD failureTime INT UNSIGNED;

ALTER TABLE {$NAMESPACE}_worker.worker_activetask
  ADD KEY `key_failuretime` (`failureTime`);
