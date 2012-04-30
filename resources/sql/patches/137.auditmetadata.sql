ALTER TABLE {$NAMESPACE}_audit.audit_comment
  ADD metadata LONGTEXT COLLATE utf8_bin NOT NULL;

UPDATE {$NAMESPACE}_audit.audit_comment
  SET metadata = '{}' WHERE metadata = '';
