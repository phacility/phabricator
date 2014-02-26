ALTER TABLE {$NAMESPACE}_herald.herald_rule
  ADD triggerObjectPHID VARCHAR(64) COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_herald.herald_rule
  ADD KEY `key_trigger` (triggerObjectPHID);
