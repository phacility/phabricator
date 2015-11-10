USE {$NAMESPACE}_repository;
DELETE x FROM repository_coverage x
LEFT JOIN repository_coverage y
  ON  x.branchID = y.branchID
  AND x.commitID = y.commitID
  AND x.pathID = y.pathID
  AND y.id > x.id
  WHERE y.id IS NOT NULL;
