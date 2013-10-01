ALTER TABLE {$NAMESPACE}_file.file
  ADD viewPolicy VARCHAR(64) NOT NULL COLLATE utf8_bin;

UPDATE {$NAMESPACE}_file.file
  SET viewPolicy = 'users' WHERE viewPolicy = '';
