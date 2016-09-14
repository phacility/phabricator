ALTER TABLE {$NAMESPACE}_repository.repository_commit
  CHANGE summary summary VARCHAR(255) NOT NULL COLLATE {$COLLATE_TEXT};
