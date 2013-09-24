ALTER TABLE {$NAMESPACE}_maniphest.maniphest_transaction
  RENAME {$NAMESPACE}_maniphest.maniphest_transaction_legacy;

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_transactionpro
  RENAME {$NAMESPACE}_maniphest.maniphest_transaction;
