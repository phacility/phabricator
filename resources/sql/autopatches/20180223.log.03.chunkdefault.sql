UPDATE {$NAMESPACE}_harbormaster.harbormaster_buildlog
  SET chunkFormat = 'text' WHERE chunkFormat = '';
