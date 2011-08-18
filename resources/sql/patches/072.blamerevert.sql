INSERT INTO phabricator_differential.differential_auxiliaryfield
  (revisionPHID, name, value, dateCreated, dateModified)
SELECT phid, 'phabricator:blame-revision', blameRevision,
    dateCreated, dateModified
  FROM phabricator_differential.differential_revision
  WHERE blameRevision != '';

ALTER TABLE phabricator_differential.differential_revision
  DROP blameRevision;


INSERT INTO phabricator_differential.differential_auxiliaryfield
  (revisionPHID, name, value, dateCreated, dateModified)
SELECT phid, 'phabricator:revert-plan', revertPlan,
    dateCreated, dateModified
  FROM phabricator_differential.differential_revision
  WHERE revertPlan != '';

ALTER TABLE phabricator_differential.differential_revision
  DROP revertPlan;
