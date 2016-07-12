ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_build
  ADD buildParameters LONGTEXT COLLATE {$COLLATE_TEXT} NOT NULL;

UPDATE {$NAMESPACE}_harbormaster.harbormaster_build
  SET buildParameters = '{}' WHERE buildParameters = '';
