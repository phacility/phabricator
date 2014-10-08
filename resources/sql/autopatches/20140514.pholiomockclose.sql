ALTER TABLE {$NAMESPACE}_pholio.pholio_mock
  ADD COLUMN status VARCHAR(12) NOT NULL COLLATE utf8_bin;

UPDATE {$NAMESPACE}_pholio.pholio_mock
  SET status = "open" WHERE status = "";
