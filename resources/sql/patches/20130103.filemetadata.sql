ALTER TABLE {$NAMESPACE}_file.file
  ADD metadata LONGTEXT COLLATE utf8_bin NOT NULL;

UPDATE {$NAMESPACE}_file.file
  SET metadata = '{}' WHERE metadata = '';
