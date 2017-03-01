ALTER TABLE {$NAMESPACE}_dashboard.dashboard
  ADD icon VARCHAR(32) NOT NULL;

UPDATE {$NAMESPACE}_dashboard.dashboard
  SET icon = 'fa-dashboard';
