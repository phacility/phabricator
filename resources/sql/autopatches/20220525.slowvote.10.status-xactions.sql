UPDATE {$NAMESPACE}_slowvote.slowvote_transaction
  SET transactionType = 'vote:status'
    WHERE transactionType = 'vote:close';

UPDATE {$NAMESPACE}_slowvote.slowvote_transaction
  SET oldValue = '"open"' WHERE
    transactionType = 'vote:status' AND oldValue IN ('0', '"0"', 'false');

UPDATE {$NAMESPACE}_slowvote.slowvote_transaction
  SET newValue = '"open"' WHERE
    transactionType = 'vote:status' AND newValue IN ('0', '"0"', 'false');

UPDATE {$NAMESPACE}_slowvote.slowvote_transaction
  SET oldValue = '"closed"' WHERE
    transactionType = 'vote:status' AND oldValue IN ('1', '"1"', 'true');

UPDATE {$NAMESPACE}_slowvote.slowvote_transaction
  SET newValue = '"closed"' WHERE
    transactionType = 'vote:status' AND newValue IN ('1', '"1"', 'true');
