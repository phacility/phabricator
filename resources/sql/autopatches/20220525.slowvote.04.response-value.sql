UPDATE {$NAMESPACE}_slowvote.slowvote_poll
  SET responseVisibility = 'visible' WHERE responseVisibility = '0';

UPDATE {$NAMESPACE}_slowvote.slowvote_poll
  SET responseVisibility = 'voters' WHERE responseVisibility = '1';

UPDATE {$NAMESPACE}_slowvote.slowvote_poll
  SET responseVisibility = 'owner' WHERE responseVisibility = '2';
