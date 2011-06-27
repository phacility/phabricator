ALTER TABLE phabricator_maniphest.maniphest_task
  ADD ownerOrdering varchar(64);

ALTER TABLE phabricator_maniphest.maniphest_task
  ADD UNIQUE KEY (phid);

ALTER TABLE phabricator_maniphest.maniphest_task
  ADD KEY (priority, status);

ALTER TABLE phabricator_maniphest.maniphest_task
  ADD KEY (status);

ALTER TABLE phabricator_maniphest.maniphest_task
  ADD KEY (ownerPHID, status);

ALTER TABLE phabricator_maniphest.maniphest_task
  ADD KEY (authorPHID, status);

ALTER TABLE phabricator_maniphest.maniphest_task
  ADD KEY (ownerOrdering);
