UPDATE {$NAMESPACE}_differential.differential_revision SET
  dateCommitted = (
    SELECT MIN(dateCreated)
    FROM {$NAMESPACE}_differential.differential_comment
    WHERE revisionID = differential_revision.id AND action = 'commit'
  )
  WHERE status = 3 AND dateCommitted IS NULL;
