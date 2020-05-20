ALTER TABLE {$NAMESPACE}_differential.differential_transaction_comment
  ADD attributes LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT};
