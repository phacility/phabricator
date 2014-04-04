ALTER TABLE {$NAMESPACE}_repository.repository_pushlog
  ADD pushEventPHID VARCHAR(64) NOT NULL COLLATE utf8_bin AFTER epoch;

ALTER TABLE {$NAMESPACE}_repository.repository_pushlog
  ADD KEY `key_event` (pushEventPHID);
