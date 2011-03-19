ALTER TABLE phabricator_repository.repository_filesystem DROP PRIMARY KEY;
ALTER TABLE phabricator_repository.repository_filesystem
  DROP KEY repositoryID_2;
ALTER TABLE phabricator_repository.repository_filesystem
  ADD PRIMARY KEY (repositoryID, parentID, pathID, svnCommit);
