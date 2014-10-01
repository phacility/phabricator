ALTER TABLE {$NAMESPACE}_pholio.pholio_transaction_comment
  ADD UNIQUE KEY `key_draft` (authorPHID, imageID, transactionPHID);
