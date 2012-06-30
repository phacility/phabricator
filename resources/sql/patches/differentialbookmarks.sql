ALTER TABLE `{$NAMESPACE}_differential`.`differential_diff`
  ADD `bookmark` VARCHAR(255) COLLATE utf8_general_ci DEFAULT NULL
  AFTER `branch`;
