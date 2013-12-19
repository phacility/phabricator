ALTER TABLE {$NAMESPACE}_repository.repository_pushlog
  ADD phid VARCHAR(64) NOT NULL COLLATE utf8_bin AFTER id;
