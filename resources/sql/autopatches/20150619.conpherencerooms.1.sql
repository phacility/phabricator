UPDATE {$NAMESPACE}_conpherence.conpherence_thread
  SET
    viewPolicy = 'obj.conpherence.members',
    editPolicy = 'obj.conpherence.members',
    joinPolicy = 'obj.conpherence.members'
  WHERE isRoom = 0;
