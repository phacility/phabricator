ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildartifact
  ADD phid VARBINARY(64) NOT NULL AFTER id;

UPDATE {$NAMESPACE}_harbormaster.harbormaster_buildartifact
  SET phid = CONCAT('PHID-HMBA-', id) WHERE phid = '';
