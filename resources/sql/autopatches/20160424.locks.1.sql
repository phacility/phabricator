ALTER TABLE {$NAMESPACE}_repository.repository_workingcopyversion
  ADD lockOwner VARCHAR(255) COLLATE {$COLLATE_TEXT};
