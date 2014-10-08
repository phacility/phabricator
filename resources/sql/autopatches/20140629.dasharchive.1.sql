ALTER TABLE {$NAMESPACE}_dashboard.dashboard_panel
  ADD isArchived BOOL NOT NULL DEFAULT 0 AFTER editPolicy;
