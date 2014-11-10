UPDATE {$NAMESPACE}_auth.auth_sshkey
  SET name = '' WHERE name IS NULL;

ALTER TABLE {$NAMESPACE}_auth.auth_sshkey
  CHANGE name name VARCHAR(255) COLLATE {$COLLATE_TEXT} NOT NULL;

UPDATE {$NAMESPACE}_auth.auth_sshkey
  SET keyType = '' WHERE keyType IS NULL;

ALTER TABLE {$NAMESPACE}_auth.auth_sshkey
  CHANGE keyType keyType VARCHAR(255) COLLATE {$COLLATE_TEXT} NOT NULL;

UPDATE {$NAMESPACE}_auth.auth_sshkey
  SET keyBody = '' WHERE keyBody IS NULL;

ALTER TABLE {$NAMESPACE}_auth.auth_sshkey
  CHANGE keyBody keyBody LONGTEXT COLLATE {$COLLATE_TEXT} NOT NULL;

UPDATE {$NAMESPACE}_auth.auth_sshkey
  SET keyComment = '' WHERE keyComment IS NULL;

ALTER TABLE {$NAMESPACE}_auth.auth_sshkey
  CHANGE keyComment keyComment VARCHAR(255) COLLATE {$COLLATE_TEXT} NOT NULL;
