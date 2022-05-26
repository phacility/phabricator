ALTER TABLE {$NAMESPACE}_slowvote.slowvote_poll
  CHANGE responseVisibility
    responseVisibility VARCHAR(32) NOT NULL COLLATE {$COLLATE_TEXT};
