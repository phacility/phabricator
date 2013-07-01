ALTER TABLE {$NAMESPACE}_conduit.conduit_methodcalllog
  ADD callerPHID VARCHAR(64);

ALTER TABLE {$NAMESPACE}_conduit.conduit_methodcalllog
  ADD KEY `key_created` (dateCreated);

ALTER TABLE {$NAMESPACE}_conduit.conduit_methodcalllog
  ADD KEY `key_method` (method);

ALTER TABLE {$NAMESPACE}_conduit.conduit_methodcalllog
  ADD KEY `key_callermethod` (callerPHID, method);

ALTER TABLE {$NAMESPACE}_conduit.conduit_connectionlog
  ADD KEY `key_created` (dateCreated);
