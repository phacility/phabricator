ALTER TABLE {$NAMESPACE}_releeph.releeph_request
  ADD COLUMN requestedObjectPHID VARCHAR(64) COLLATE utf8_bin NOT NULL;

ALTER TABLE {$NAMESPACE}_releeph.releeph_request
  ADD KEY `key_requestedObject` (requestedObjectPHID);
