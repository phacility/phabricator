ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  ADD ownerOrdering varchar(64);

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  ADD UNIQUE KEY (phid);

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  ADD KEY (priority, status);

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  ADD KEY (status);

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  ADD KEY (ownerPHID, status);

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  ADD KEY (authorPHID, status);

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  ADD KEY (ownerOrdering);
