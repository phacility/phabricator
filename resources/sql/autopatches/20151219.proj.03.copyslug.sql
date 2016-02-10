UPDATE {$NAMESPACE}_project.project
  SET primarySlug = TRIM(TRAILING "/" FROM phrictionSlug);
