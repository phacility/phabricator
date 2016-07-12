ALTER TABLE {$NAMESPACE}_differential.differential_diff
  DROP COLUMN arcanistProjectPHID;

ALTER TABLE {$NAMESPACE}_differential.differential_revision
  DROP COLUMN arcanistProjectPHID;

ALTER TABLE {$NAMESPACE}_releeph.releeph_project
  DROP COLUMN arcanistProjectID;

DROP TABLE {$NAMESPACE}_repository.repository_arcanistproject;
