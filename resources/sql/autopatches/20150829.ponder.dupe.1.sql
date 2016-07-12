UPDATE {$NAMESPACE}_ponder.ponder_question
  SET status = 'invalid' WHERE status = 'duplicate';
