CREATE TABLE {$NAMESPACE}_file.file_chunk (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  chunkHandle BINARY(12) NOT NULL,
  byteStart BIGINT UNSIGNED NOT NULL,
  byteEnd BIGINT UNSIGNED NOT NULL,
  dataFilePHID VARBINARY(64),
  KEY `key_file` (chunkhandle, byteStart, byteEnd),
  KEY `key_data` (dataFilePHID)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
