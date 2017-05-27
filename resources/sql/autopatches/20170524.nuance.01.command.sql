ALTER TABLE {$NAMESPACE}_nuance.nuance_itemcommand
  ADD dateCreated INT UNSIGNED NOT NULL;

ALTER TABLE {$NAMESPACE}_nuance.nuance_itemcommand
  ADD dateModified INT UNSIGNED NOT NULL;

ALTER TABLE {$NAMESPACE}_nuance.nuance_itemcommand
  ADD queuePHID VARBINARY(64);
