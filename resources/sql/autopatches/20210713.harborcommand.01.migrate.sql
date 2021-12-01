INSERT IGNORE INTO {$NAMESPACE}_harbormaster.harbormaster_buildmessage
  (authorPHID, receiverPHID, type, isConsumed, dateCreated, dateModified)
  SELECT authorPHID, targetPHID, command, 0, dateCreated, dateModified
    FROM {$NAMESPACE}_harbormaster.harbormaster_buildcommand;
