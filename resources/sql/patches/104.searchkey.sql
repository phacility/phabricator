ALTER TABLE {$NAMESPACE}_search.search_query
  DROP authorPHID;

ALTER TABLE {$NAMESPACE}_search.search_query
  ADD queryKey VARCHAR(12) NOT NULL;

/* Preserve URIs for old queries in case anyone has them bookmarked. */
UPDATE {$NAMESPACE}_search.search_query
  SET queryKey = id;

ALTER TABLE {$NAMESPACE}_search.search_query
  ADD UNIQUE KEY (queryKey);

/* NOTE: Accidentally added this as 104, merging. */
UPDATE {$NAMESPACE}_project.project SET status = IF(status = 5, 100, 0);
