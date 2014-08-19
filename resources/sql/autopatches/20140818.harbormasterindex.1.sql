ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildlog
  ADD KEY `key_buildtarget` (buildTargetPHID);
