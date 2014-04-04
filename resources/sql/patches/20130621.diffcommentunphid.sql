ALTER TABLE {$NAMESPACE}_differential.differential_comment
  DROP phid;

ALTER TABLE {$NAMESPACE}_differential.differential_transaction_comment
  ADD legacyCommentID INT UNSIGNED;
