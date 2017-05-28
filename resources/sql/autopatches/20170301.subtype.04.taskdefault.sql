UPDATE {$NAMESPACE}_maniphest.maniphest_task
  SET subtype = 'default' WHERE subtype = '';
