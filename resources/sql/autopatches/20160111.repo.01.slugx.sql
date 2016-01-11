UPDATE {$NAMESPACE}_repository.repository_transaction
  SET transactionType = 'repo:slug' WHERE transactionType = 'repo:clone-name';
