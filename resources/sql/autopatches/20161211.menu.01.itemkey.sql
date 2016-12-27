ALTER TABLE {$NAMESPACE}_search.search_profilepanelconfiguration
  CHANGE panelKey menuItemKey VARCHAR(64) NOT NULL COLLATE {$COLLATE_TEXT};
