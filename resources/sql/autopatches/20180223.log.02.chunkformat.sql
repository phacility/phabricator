ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildlog
  ADD chunkFormat VARCHAR(32) NOT NULL COLLATE {$COLLATE_TEXT};
