/* This cleans up some errant transactions, see T8483. */

DELETE FROM {$NAMESPACE}_differential.differential_transaction
  WHERE transactionType = 'core:inlinestate' AND newValue = 'null';
