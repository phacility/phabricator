UPDATE {$NAMESPACE}_owners.owners_package
  SET status = 'active' WHERE status = '';
