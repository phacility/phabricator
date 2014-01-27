ALTER TABLE {$NAMESPACE}_differential.differential_diff
  ADD COLUMN repositoryPHID VARCHAR(64) COLLATE utf8_bin AFTER authorPHID;
