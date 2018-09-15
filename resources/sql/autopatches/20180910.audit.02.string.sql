ALTER TABLE {$NAMESPACE}_repository.repository_commit
  CHANGE auditStatus auditStatus VARCHAR(32) NOT NULL COLLATE {$COLLATE_TEXT};
