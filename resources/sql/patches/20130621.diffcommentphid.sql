ALTER TABLE {$NAMESPACE}_differential.differential_comment
  ADD phid VARCHAR(64) NOT NULL COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_differential.differential_comment
  ADD KEY `key_phid` (phid);
