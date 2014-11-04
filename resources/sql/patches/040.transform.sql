CREATE TABLE {$NAMESPACE}_file.file_transformedfile (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  originalPHID varchar(64) COLLATE `binary` NOT NULL,
  transform varchar(255) COLLATE `binary` NOT NULL,
  unique key (originalPHID, transform),
  transformedPHID varchar(64) BINARY NOT NULL,
  key (transformedPHID),
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL
);
