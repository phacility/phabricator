ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildmessage
  CHANGE buildTargetPHID receiverPHID VARBINARY(64) NOT NULL;
