UPDATE {$NAMESPACE}_slowvote.slowvote_poll
  SET method = 'plurality' WHERE method = '0';

UPDATE {$NAMESPACE}_slowvote.slowvote_poll
  SET method = 'approval' WHERE method = '1';
