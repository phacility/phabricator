ALTER TABLE phabricator_worker.worker_task
  ADD dataID int unsigned;

ALTER TABLE phabricator_worker.worker_task
  ADD UNIQUE KEY (dataID);

UPDATE phabricator_worker.worker_task t,
       phabricator_worker.worker_taskdata d
  SET t.dataID = d.id
  WHERE d.taskID = t.id;

ALTER TABLE phabricator_worker.worker_taskdata
  DROP taskID;
