ALTER TABLE {$NAMESPACE}_dashboard.dashboard
  ADD COLUMN layoutConfig LONGTEXT NOT NULL COLLATE utf8_bin AFTER name;

UPDATE {$NAMESPACE}_dashboard.dashboard SET layoutConfig  = '[]';
