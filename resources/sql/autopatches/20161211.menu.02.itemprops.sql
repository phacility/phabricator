ALTER TABLE {$NAMESPACE}_search.search_profilepanelconfiguration
  CHANGE panelProperties menuItemProperties
    LONGTEXT NOT NULL COLLATE {$COLLATE_TEXT};
