ALTER TABLE {$NAMESPACE}_differential.differential_transaction_comment
  DROP KEY `key_draft`;

ALTER TABLE {$NAMESPACE}_differential.differential_transaction_comment
  ADD KEY `key_changeset` (changesetID);

ALTER TABLE {$NAMESPACE}_differential.differential_transaction_comment
  ADD KEY `key_draft` (authorPHID, transactionPHID);

ALTER TABLE {$NAMESPACE}_differential.differential_transaction_comment
  ADD KEY `key_revision` (revisionPHID);

ALTER TABLE {$NAMESPACE}_differential.differential_transaction_comment
  ADD KEY `key_legacy` (legacyCommentID);
