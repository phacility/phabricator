UPDATE {$NAMESPACE}_repository.repository_auditrequest
  SET auditStatus = 'accepted' WHERE auditStatus = 'closed';

DELETE FROM {$NAMESPACE}_repository.repository_auditrequest
  WHERE auditStatus IN ('', 'cc', 'audit-not-required');
