ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  ADD properties LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT};

UPDATE {$NAMESPACE}_maniphest.maniphest_task
  SET properties = '{}' WHERE properties = '';
