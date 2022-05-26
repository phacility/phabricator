UPDATE {$NAMESPACE}_slowvote.slowvote_transaction
  SET oldValue = '"visible"' WHERE
    transactionType = 'vote:responses' AND oldValue IN ('0', '"0"');

UPDATE {$NAMESPACE}_slowvote.slowvote_transaction
  SET newValue = '"visible"' WHERE
    transactionType = 'vote:responses' AND newValue IN ('0', '"0"');

UPDATE {$NAMESPACE}_slowvote.slowvote_transaction
  SET oldValue = '"voters"' WHERE
    transactionType = 'vote:responses' AND oldValue IN ('1', '"1"');

UPDATE {$NAMESPACE}_slowvote.slowvote_transaction
  SET newValue = '"voters"' WHERE
    transactionType = 'vote:responses' AND newValue IN ('1', '"1"');

UPDATE {$NAMESPACE}_slowvote.slowvote_transaction
  SET oldValue = '"owner"' WHERE
    transactionType = 'vote:responses' AND oldValue IN ('2', '"2"');

UPDATE {$NAMESPACE}_slowvote.slowvote_transaction
  SET newValue = '"owner"' WHERE
    transactionType = 'vote:responses' AND newValue IN ('2', '"2"');
