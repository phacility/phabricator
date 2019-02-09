UPDATE {$NAMESPACE}_owners.owners_package
  SET auditingState = IF(auditingEnabled = 0, 'none', 'audit');
