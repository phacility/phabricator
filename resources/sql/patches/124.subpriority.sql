ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  ADD subpriority DOUBLE NOT NULL;

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  ADD KEY (priority, subpriority);

/* Seed the subpriority column with reasonable values that keep order stable. */
UPDATE {$NAMESPACE}_maniphest.maniphest_task
  SET subpriority = (UNIX_TIMESTAMP() - dateModified);
