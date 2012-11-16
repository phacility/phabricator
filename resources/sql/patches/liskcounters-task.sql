ALTER TABLE `{$NAMESPACE}_worker`.worker_task
  CHANGE id id INT UNSIGNED NOT NULL;

RENAME TABLE `{$NAMESPACE}_worker`.worker_task
  TO `{$NAMESPACE}_worker`.worker_activetask;

UPDATE `{$NAMESPACE}_worker`.lisk_counter
  SET counterName = 'worker_activetask' WHERE counterName = 'worker_task';
