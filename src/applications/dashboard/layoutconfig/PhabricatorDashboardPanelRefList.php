<?php

final class PhabricatorDashboardPanelRefList
  extends Phobject {

  private $refs;
  private $columns;

  public static function newFromDictionary($config) {
    if (!is_array($config)) {
      $config = array();
    }

    $mode_map = PhabricatorDashboardLayoutMode::getAllLayoutModes();
    $mode_key = idx($config, 'layoutMode');
    if (!isset($mode_map[$mode_key])) {
      $mode_key = head_key($mode_map);
    }
    $mode = $mode_map[$mode_key];

    $columns = $mode->getLayoutModeColumns();
    $columns = mpull($columns, null, 'getColumnKey');
    $default_column = head($columns);

    $panels = idx($config, 'panels');
    if (!is_array($panels)) {
      $panels = array();
    }

    $seen_panels = array();
    $refs = array();
    foreach ($panels as $panel) {
      $panel_phid = idx($panel, 'panelPHID');
      if (!strlen($panel_phid)) {
        continue;
      }

      $panel_key = idx($panel, 'panelKey');
      if (!strlen($panel_key)) {
        continue;
      }

      if (isset($seen_panels[$panel_key])) {
        continue;
      }
      $seen_panels[$panel_key] = true;

      $column_key = idx($panel, 'columnKey');
      $column = idx($columns, $column_key, $default_column);

      $ref = id(new PhabricatorDashboardPanelRef())
        ->setPanelPHID($panel_phid)
        ->setPanelKey($panel_key)
        ->setColumnKey($column->getColumnKey());

      $column->addPanelRef($ref);
      $refs[] = $ref;
    }

    $list = new self();

    $list->columns = $columns;
    $list->refs = $refs;

    return $list;
  }

  public function getColumns() {
    return $this->columns;
  }

  public function getPanelRefs() {
    return $this->refs;
  }

}
