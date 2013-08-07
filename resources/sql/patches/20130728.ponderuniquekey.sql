ALTER TABLE {$NAMESPACE}_ponder.ponder_answer
  ADD UNIQUE KEY `key_oneanswerperquestion` (questionID, authorPHID);
