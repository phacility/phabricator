ALTER TABLE {$NAMESPACE}_repository.repository
  ADD COLUMN pushPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin;

UPDATE {$NAMESPACE}_repository.repository
  SET pushPolicy = 'users' WHERE pushPolicy = '';
