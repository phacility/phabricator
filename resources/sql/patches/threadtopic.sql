ALTER TABLE {$NAMESPACE}_differential.differential_revision
  ADD originalTitle varchar(255) NOT NULL AFTER title;
UPDATE {$NAMESPACE}_differential.differential_revision SET
  originalTitle = title;

ALTER TABLE {$NAMESPACE}_owners.owners_package
  ADD originalName varchar(255) NOT NULL AFTER name;
UPDATE {$NAMESPACE}_owners.owners_package SET
  originalName = name;

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  ADD originalTitle text NOT NULL AFTER title;
UPDATE {$NAMESPACE}_maniphest.maniphest_task SET
  originalTitle = title;
