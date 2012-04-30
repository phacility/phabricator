ALTER TABLE {$NAMESPACE}_repository.repository_filesystem DROP PRIMARY KEY;
ALTER TABLE {$NAMESPACE}_repository.repository_filesystem
  DROP KEY repositoryID_2;
ALTER TABLE {$NAMESPACE}_repository.repository_filesystem
  ADD PRIMARY KEY (repositoryID, parentID, pathID, svnCommit);
