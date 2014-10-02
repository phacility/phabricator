UPDATE {$NAMESPACE}_maniphest.maniphest_task
  SET mailKey = SUBSTRING(mailKey, 1, 20) WHERE LENGTH(mailKey) > 20;
