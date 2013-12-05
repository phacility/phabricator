ALTER TABLE {$NAMESPACE}_drydock.drydock_resource
ADD COLUMN blueprintPHID VARCHAR(64) NOT NULL COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_drydock.drydock_resource
DROP COLUMN blueprintClass;
