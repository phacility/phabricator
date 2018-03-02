ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildlog
  ADD lineMap LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT};
