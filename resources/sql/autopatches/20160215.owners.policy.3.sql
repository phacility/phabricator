UPDATE {$NAMESPACE}_owners.owners_package
  SET viewPolicy = 'users' WHERE viewPolicy = '';
