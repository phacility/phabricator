ALTER TABLE phabricator_differential.differential_comment
  ADD contentSource VARCHAR(255);

ALTER TABLE phabricator_maniphest.maniphest_transaction
  ADD contentSource VARCHAR(255);
