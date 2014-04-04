ALTER TABLE {$NAMESPACE}_differential.differential_diff
  ADD phid VARCHAR(64) NOT NULL COLLATE utf8_bin AFTER id;
