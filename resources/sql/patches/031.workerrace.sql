ALTER TABLE {$NAMESPACE}_worker.worker_task
  ADD dataID int unsigned;

ALTER TABLE {$NAMESPACE}_worker.worker_task
  ADD UNIQUE KEY (dataID);

UPDATE {$NAMESPACE}_worker.worker_task t,
       {$NAMESPACE}_worker.worker_taskdata d
  SET t.dataID = d.id
  WHERE d.taskID = t.id;

ALTER TABLE {$NAMESPACE}_worker.worker_taskdata
  DROP taskID;
