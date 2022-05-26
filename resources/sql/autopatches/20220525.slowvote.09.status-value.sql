UPDATE {$NAMESPACE}_slowvote.slowvote_poll
  SET status = 'open' WHERE status = '0';

UPDATE {$NAMESPACE}_slowvote.slowvote_poll
  SET status = 'closed' WHERE status = '1';
