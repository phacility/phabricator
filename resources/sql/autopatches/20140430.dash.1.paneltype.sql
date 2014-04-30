ALTER TABLE {$NAMESPACE}_dashboard.dashboard_panel
  ADD panelType VARCHAR(64) NOT NULL COLLATE utf8_bin AFTER name;
