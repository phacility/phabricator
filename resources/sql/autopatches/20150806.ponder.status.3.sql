UPDATE {$NAMESPACE}_ponder.ponder_question
  SET status = 'resolved' WHERE status = '1';
