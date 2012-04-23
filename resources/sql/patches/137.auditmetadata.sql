ALTER TABLE phabricator_audit.audit_comment
  ADD metadata LONGTEXT COLLATE utf8_bin NOT NULL;

UPDATE phabricator_audit.audit_comment
  SET metadata = '{}' WHERE metadata = '';
