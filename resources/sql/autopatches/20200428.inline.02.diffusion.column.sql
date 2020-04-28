ALTER TABLE {$NAMESPACE}_audit.audit_transaction_comment
  ADD attributes LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT};
