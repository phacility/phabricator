/* Migrate old badge quality transactions */

UPDATE {$NAMESPACE}_badges.badges_transaction
  SET oldValue = 140
  WHERE oldValue = '"grey"' AND transactionType = 'badges:quality';

UPDATE {$NAMESPACE}_badges.badges_transaction
  SET oldValue = 120
  WHERE oldValue = '"white"' AND transactionType = 'badges:quality';

UPDATE {$NAMESPACE}_badges.badges_transaction
  SET oldValue = 100
  WHERE oldValue = '"green"' AND transactionType = 'badges:quality';

UPDATE {$NAMESPACE}_badges.badges_transaction
  SET oldValue = 80
  WHERE oldValue = '"blue"' AND transactionType = 'badges:quality';

UPDATE {$NAMESPACE}_badges.badges_transaction
  SET oldValue = 60
  WHERE oldValue = '"indigo"' AND transactionType = 'badges:quality';

UPDATE {$NAMESPACE}_badges.badges_transaction
  SET oldValue = 40
  WHERE oldValue = '"orange"' AND transactionType = 'badges:quality';

UPDATE {$NAMESPACE}_badges.badges_transaction
  SET oldValue = 20
  WHERE oldValue = '"yellow"' AND transactionType = 'badges:quality';



UPDATE {$NAMESPACE}_badges.badges_transaction
  SET newValue = 140
  WHERE newValue = '"grey"' AND transactionType = 'badges:quality';

UPDATE {$NAMESPACE}_badges.badges_transaction
  SET newValue = 120
  WHERE newValue = '"white"' AND transactionType = 'badges:quality';

UPDATE {$NAMESPACE}_badges.badges_transaction
  SET newValue = 100
  WHERE newValue = '"green"' AND transactionType = 'badges:quality';

UPDATE {$NAMESPACE}_badges.badges_transaction
  SET newValue = 80
  WHERE newValue = '"blue"' AND transactionType = 'badges:quality';

UPDATE {$NAMESPACE}_badges.badges_transaction
  SET newValue = 60
  WHERE newValue = '"indigo"' AND transactionType = 'badges:quality';

UPDATE {$NAMESPACE}_badges.badges_transaction
  SET newValue = 40
  WHERE newValue = '"orange"' AND transactionType = 'badges:quality';

UPDATE {$NAMESPACE}_badges.badges_transaction
  SET newValue = 20
  WHERE newValue = '"yellow"' AND transactionType = 'badges:quality';
