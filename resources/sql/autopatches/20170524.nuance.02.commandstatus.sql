ALTER TABLE {$NAMESPACE}_nuance.nuance_itemcommand
  ADD status VARCHAR(64) NOT NULL;

UPDATE {$NAMESPACE}_nuance.nuance_itemcommand
  SET status = 'done' WHERE status = '';
