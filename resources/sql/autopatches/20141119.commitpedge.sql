INSERT IGNORE INTO {$NAMESPACE}_repository.edge
  (src, type, dst, dateCreated, seq)
  SELECT src, 41, dst, dateCreated, seq
  FROM {$NAMESPACE}_repository.edge
  WHERE type = 15;

INSERT IGNORE INTO {$NAMESPACE}_project.edge
  (src, type, dst, dateCreated, seq)
  SELECT src, 42, dst, dateCreated, seq
  FROM {$NAMESPACE}_project.edge
  WHERE type = 16;
