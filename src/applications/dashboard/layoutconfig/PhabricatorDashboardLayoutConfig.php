<?php

final class PhabricatorDashboardLayoutConfig {

  const MODE_FULL                = 'layout-mode-full';
  const MODE_HALF_AND_HALF       = 'layout-mode-half-and-half';
  const MODE_THIRD_AND_THIRDS    = 'layout-mode-third-and-thirds';
  const MODE_THIRDS_AND_THIRD    = 'layout-mode-thirds-and-third';

  private $layoutMode     = self::MODE_FULL;
  private $panelLocations = array();

  public function setLayoutMode($mode) {
    $this->layoutMode = $mode;
    return $this;
  }
  public function getLayoutMode() {
    return $this->layoutMode;
  }

  public function setPanelLocation($which_column, $panel_phid) {
    $this->panelLocations[$which_column][] = $panel_phid;
    return $this;
  }

  public function setPanelLocations(array $locations) {
    $this->panelLocations = $locations;
    return $this;
  }

  public function getPanelLocations() {
    return $this->panelLocations;
  }

  public function replacePanel($old_phid, $new_phid) {
    $locations = $this->getPanelLocations();
    foreach ($locations as $column => $panel_phids) {
      foreach ($panel_phids as $key => $panel_phid) {
        if ($panel_phid == $old_phid) {
          $locations[$column][$key] = $new_phid;
        }
      }
    }
    return $this->setPanelLocations($locations);
  }

  public function removePanel($panel_phid) {
    $panel_location_grid = $this->getPanelLocations();
    foreach ($panel_location_grid as $column => $panel_columns) {
      $found_old_column = array_search($panel_phid, $panel_columns);
      if ($found_old_column !== false) {
        $new_panel_columns = $panel_columns;
        array_splice(
          $new_panel_columns,
          $found_old_column,
          1,
          array());
        $panel_location_grid[$column] = $new_panel_columns;
        break;
      }
    }
    $this->setPanelLocations($panel_location_grid);
  }

  public function getDefaultPanelLocations() {
    switch ($this->getLayoutMode()) {
      case self::MODE_HALF_AND_HALF:
      case self::MODE_THIRD_AND_THIRDS:
      case self::MODE_THIRDS_AND_THIRD:
        $locations = array(array(), array());
        break;
      case self::MODE_FULL:
      default:
        $locations = array(array());
        break;
    }
    return $locations;
  }

  public function getColumnClass($column_index, $grippable = false) {
    switch ($this->getLayoutMode()) {
      case self::MODE_HALF_AND_HALF:
        $class = 'half';
        break;
      case self::MODE_THIRD_AND_THIRDS:
        if ($column_index) {
          $class = 'thirds';
        } else {
          $class = 'third';
        }
        break;
      case self::MODE_THIRDS_AND_THIRD:
        if ($column_index) {
          $class = 'third';
        } else {
          $class = 'thirds';
        }
        break;
      case self::MODE_FULL:
      default:
        $class = null;
        break;
    }
    if ($grippable) {
      $class .= ' grippable';
    }
    return $class;
  }

  public function isMultiColumnLayout() {
    return $this->getLayoutMode() != self::MODE_FULL;
  }

  public function getColumnSelectOptions() {
    $options = array();

    switch ($this->getLayoutMode()) {
      case self::MODE_HALF_AND_HALF:
      case self::MODE_THIRD_AND_THIRDS:
      case self::MODE_THIRDS_AND_THIRD:
        return array(
          0 => pht('Left'),
          1 => pht('Right'),
        );
        break;
      case self::MODE_FULL:
        throw new Exception(pht('There is only one column in mode full.'));
        break;
      default:
        throw new Exception(pht('Unknown layout mode!'));
        break;
    }

    return $options;
  }

  public static function getLayoutModeSelectOptions() {
    return array(
      self::MODE_FULL             => pht('One full-width column'),
      self::MODE_HALF_AND_HALF    => pht('Two columns, 1/2 and 1/2'),
      self::MODE_THIRD_AND_THIRDS => pht('Two columns, 1/3 and 2/3'),
      self::MODE_THIRDS_AND_THIRD => pht('Two columns, 2/3 and 1/3'),
    );
  }

  public static function newFromDictionary(array $dict) {
    $layout_config = id(new PhabricatorDashboardLayoutConfig())
      ->setLayoutMode(idx($dict, 'layoutMode', self::MODE_FULL));
    $layout_config->setPanelLocations(idx(
      $dict,
      'panelLocations',
      $layout_config->getDefaultPanelLocations()));

    return $layout_config;
  }

  public function toDictionary() {
    return array(
      'layoutMode' => $this->getLayoutMode(),
      'panelLocations' => $this->getPanelLocations(),
    );
  }

}
