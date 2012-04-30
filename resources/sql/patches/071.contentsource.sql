ALTER TABLE {$NAMESPACE}_differential.differential_comment
  ADD contentSource VARCHAR(255);

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_transaction
  ADD contentSource VARCHAR(255);
