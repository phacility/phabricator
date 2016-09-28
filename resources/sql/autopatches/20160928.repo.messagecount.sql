/* Reset message counts to fix the bug in T11705 which caused some of them to
   become very large. */
UPDATE {$NAMESPACE}_repository.repository_statusmessage
  SET messageCount = 0;
