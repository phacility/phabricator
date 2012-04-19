UPDATE phabricator_differential.differential_revision SET
  dateCommitted = (
    SELECT MIN(dateCreated)
    FROM phabricator_differential.differential_comment
    WHERE revisionID = differential_revision.id AND action = 'commit'
  )
  WHERE status = 3 AND dateCommitted IS NULL;
