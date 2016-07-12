TRUNCATE {$NAMESPACE}_drydock.drydock_log;

ALTER TABLE {$NAMESPACE}_drydock.drydock_log
  DROP resourceID;

ALTER TABLE {$NAMESPACE}_drydock.drydock_log
  DROP leaseID;

ALTER TABLE {$NAMESPACE}_drydock.drydock_log
  DROP message;

ALTER TABLE {$NAMESPACE}_drydock.drydock_log
  ADD blueprintPHID VARBINARY(64);

ALTER TABLE {$NAMESPACE}_drydock.drydock_log
  ADD resourcePHID VARBINARY(64);

ALTER TABLE {$NAMESPACE}_drydock.drydock_log
  ADD leasePHID VARBINARY(64);

ALTER TABLE {$NAMESPACE}_drydock.drydock_log
  ADD type VARCHAR(64) NOT NULL COLLATE {$COLLATE_TEXT};

ALTER TABLE {$NAMESPACE}_drydock.drydock_log
  ADD data LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT};
