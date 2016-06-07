UPDATE {$NAMESPACE}_user.user_preferences
  SET dateCreated = UNIX_TIMESTAMP() WHERE dateCreated = 0;
