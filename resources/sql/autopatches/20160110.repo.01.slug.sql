ALTER TABLE {$NAMESPACE}_repository.repository
  ADD repositorySlug VARCHAR(64) COLLATE {$COLLATE_SORT};

ALTER TABLE {$NAMESPACE}_repository.repository
  ADD UNIQUE KEY `key_slug` (repositorySlug);
