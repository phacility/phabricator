ALTER TABLE {$NAMESPACE}_ponder.ponder_question
  ADD editPolicy VARBINARY(64) NOT NULL;

ALTER TABLE {$NAMESPACE}_ponder.ponder_question
  ADD viewPolicy VARBINARY(64) NOT NULL;
