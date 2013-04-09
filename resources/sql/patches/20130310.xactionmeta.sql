ALTER TABLE `{$NAMESPACE}_file`.`macro_transaction`
  ADD metadata LONGTEXT NOT NULL COLLATE utf8_bin;
UPDATE `{$NAMESPACE}_file`.macro_transaction SET metadata = '{}'
  WHERE metadata = '';

ALTER TABLE `{$NAMESPACE}_pholio`.`pholio_transaction`
  ADD metadata LONGTEXT NOT NULL COLLATE utf8_bin;
UPDATE `{$NAMESPACE}_pholio`.pholio_transaction SET metadata = '{}'
  WHERE metadata = '';

ALTER TABLE `{$NAMESPACE}_config`.`config_transaction`
  ADD metadata LONGTEXT NOT NULL COLLATE utf8_bin;
UPDATE `{$NAMESPACE}_config`.config_transaction SET metadata = '{}'
  WHERE metadata = '';

ALTER TABLE `{$NAMESPACE}_conpherence`.`conpherence_transaction`
  ADD metadata LONGTEXT NOT NULL COLLATE utf8_bin;
UPDATE `{$NAMESPACE}_conpherence`.conpherence_transaction SET metadata = '{}'
  WHERE metadata = '';

ALTER TABLE `{$NAMESPACE}_phlux`.`phlux_transaction`
  ADD metadata LONGTEXT NOT NULL COLLATE utf8_bin;
UPDATE `{$NAMESPACE}_phlux`.phlux_transaction SET metadata = '{}'
  WHERE metadata = '';
