<?php

final class PhabricatorDashboardProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'dashboard';

  const FIELD_DASHBOARD = 'dashboardPHID';

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

  public function canMakeDefault(
    PhabricatorProfileMenuItemConfiguration $config) {
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

  public function newPageContent(
    PhabricatorProfileMenuItemConfiguration $config) {
    $viewer = $this->getViewer();

    $dashboard_phid = $config->getMenuItemProperty('dashboardPHID');

    // Reload the dashboard to attach panels, which we need for rendering.
    $dashboard = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($dashboard_phid))
      ->needPanels(true)
      ->executeOne();
    if (!$dashboard) {
      return null;
    }

    $engine = id(new PhabricatorDashboardRenderingEngine())
      ->setViewer($viewer)
      ->setDashboard($dashboard);

    return $engine->renderDashboard();
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
      id(new PhabricatorDatasourceEditField())
        ->setKey(self::FIELD_DASHBOARD)
        ->setLabel(pht('Dashboard'))
        ->setIsRequired(true)
        ->setDatasource(new PhabricatorDashboardDatasource())
        ->setSingleValue($config->getMenuItemProperty('dashboardPHID')),
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setValue($this->getName($config)),
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
    $href = $this->getItemViewURI($config);
    $action_href = '/dashboard/arrange/'.$dashboard->getID().'/';

    $item = $this->newItem()
      ->setHref($href)
      ->setName($name)
      ->setIcon($icon)
      ->setActionIcon('fa-pencil', $action_href);

    return array(
      $item,
    );
  }

  public function validateTransactions(
    PhabricatorProfileMenuItemConfiguration $config,
    $field_key,
    $value,
    array $xactions) {

    $viewer = $this->getViewer();
    $errors = array();

    if ($field_key == self::FIELD_DASHBOARD) {
      if ($this->isEmptyTransaction($value, $xactions)) {
       $errors[] = $this->newRequiredError(
         pht('You must choose a dashboard.'),
         $field_key);
      }

      foreach ($xactions as $xaction) {
        $new = $xaction['new'];

        if (!$new) {
          continue;
        }

        if ($new === $value) {
          continue;
        }

        $dashboards = id(new PhabricatorDashboardQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($new))
          ->execute();
        if (!$dashboards) {
          $errors[] = $this->newInvalidError(
            pht(
              'Dashboard "%s" is not a valid dashboard which you have '.
              'permission to see.',
              $new),
            $xaction['xaction']);
        }
      }
    }

    return $errors;
  }

}
