INSERT IGNORE INTO {$NAMESPACE}_maniphest.edge (src, type, dst)
  SELECT taskPHID, 21, subscriberPHID
  FROM {$NAMESPACE}_maniphest.maniphest_tasksubscriber
  WHERE subscriberPHID != '';

INSERT IGNORE INTO {$NAMESPACE}_maniphest.edge (src, type, dst)
  SELECT subscriberPHID, 22, taskPHID
  FROM {$NAMESPACE}_maniphest.maniphest_tasksubscriber
  WHERE subscriberPHID != '';
