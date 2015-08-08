UPDATE {$NAMESPACE}_ponder.ponder_question
  SET editPolicy = authorPHID WHERE editPolicy = '';
