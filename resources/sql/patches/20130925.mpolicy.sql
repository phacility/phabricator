ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  ADD viewPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  ADD editPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin;

UPDATE {$NAMESPACE}_maniphest.maniphest_task
  SET viewPolicy = 'users' WHERE viewPolicy = '';

UPDATE {$NAMESPACE}_maniphest.maniphest_task
  SET editPolicy = 'users' WHERE editPolicy = '';
