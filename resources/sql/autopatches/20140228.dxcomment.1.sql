/* Make this column nullable. */

ALTER TABLE {$NAMESPACE}_differential.differential_transaction_comment
  CHANGE revisionPHID revisionPHID VARCHAR(64) COLLATE utf8_bin;
