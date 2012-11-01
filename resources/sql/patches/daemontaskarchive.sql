CREATE TABLE {$NAMESPACE}_worker.worker_archivetask (
  id INT UNSIGNED PRIMARY KEY,
  taskClass VARCHAR(255) NOT NULL COLLATE utf8_bin,
  leaseOwner VARCHAR(255) COLLATE utf8_bin,
  leaseExpires INT UNSIGNED,
  failureCount INT UNSIGNED NOT NULL,
  dataID INT UNSIGNED NOT NULL,
  result INT UNSIGNED NOT NULL,
  duration BIGINT UNSIGNED NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  key(dateCreated)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
