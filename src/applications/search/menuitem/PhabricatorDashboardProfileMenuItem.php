<?php

final class PhabricatorDashboardProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'dashboard';

  private $dashboard;

  public function getMenuItemTypeIcon() {
    return 'fa-dashboard';
  }

  public function getMenuItemTypeName() {
    return pht('Dashboard');
  }

  public function canAddToObject($object) {
    return true;
  }

  public function attachDashboard($dashboard) {
    $this->dashboard = $dashboard;
    return $this;
  }

  public function getDashboard() {
    $dashboard = $this->dashboard;
    if (!$dashboard) {
      return null;
    } else if ($dashboard->isArchived()) {
      return null;
    }
    return $dashboard;
  }

  public function willBuildNavigationItems(array $items) {
    $viewer = $this->getViewer();
    $dashboard_phids = array();
    foreach ($items as $item) {
      $dashboard_phids[] = $item->getMenuItemProperty('dashboardPHID');
    }

    $dashboards = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withPHIDs($dashboard_phids)
      ->execute();

    $dashboards = mpull($dashboards, null, 'getPHID');
    foreach ($items as $item) {
      $dashboard_phid = $item->getMenuItemProperty('dashboardPHID');
      $dashboard = idx($dashboards, $dashboard_phid, null);
      $item->getMenuItem()->attachDashboard($dashboard);
    }
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $dashboard = $this->getDashboard();
    if (!$dashboard) {
      return pht('(Restricted/Invalid Dashboard)');
    }
    if (strlen($this->getName($config))) {
      return $this->getName($config);
    } else {
      return $dashboard->getName();
    }
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setValue($this->getName($config)),
      id(new PhabricatorDatasourceEditField())
        ->setKey('dashboardPHID')
        ->setLabel(pht('Dashboard'))
        ->setDatasource(new PhabricatorDashboardDatasource())
        ->setSingleValue($config->getMenuItemProperty('dashboardPHID')),
    );
  }

  private function getName(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $config->getMenuItemProperty('name');
  }

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {

    $dashboard = $this->getDashboard();
    if (!$dashboard) {
      return array();
    }

    $icon = $dashboard->getIcon();
    $name = $this->getDisplayName($config);
    $href = $dashboard->getViewURI();

    $item = $this->newItem()
      ->setHref($href)
      ->setName($name)
      ->setIcon($icon);

    return array(
      $item,
    );
  }

}
