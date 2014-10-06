CREATE TABLE `{$NAMESPACE}_harbormaster`.`lisk_counter` (
  counterName VARCHAR(64) COLLATE utf8_bin PRIMARY KEY,
  counterValue BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
