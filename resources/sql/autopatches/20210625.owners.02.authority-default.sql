UPDATE {$NAMESPACE}_owners.owners_package
  SET authorityMode = 'strong'
  WHERE authorityMode = '';
