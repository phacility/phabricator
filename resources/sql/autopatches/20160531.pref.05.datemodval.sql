UPDATE {$NAMESPACE}_user.user_preferences
  SET dateModified = UNIX_TIMESTAMP() WHERE dateModified = 0;
