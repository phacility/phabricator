UPDATE {$NAMESPACE}_ponder.ponder_question
  SET status = 'open' WHERE status = '0';
