<?php

final class PhabricatorDashboardProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'dashboard';

  const FIELD_DASHBOARD = 'dashboardPHID';

  private $dashboard;
  private $dashboardHandle;

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

  private function attachDashboard(PhabricatorDashboard $dashboard = null) {
    $this->dashboard = $dashboard;
    return $this;
  }

  private function getDashboard() {
    return $this->dashboard;
  }

  public function getAffectedObjectPHIDs(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      $this->getDashboardPHID($config),
    );
  }

  public function newPageContent(
    PhabricatorProfileMenuItemConfiguration $config) {
    $viewer = $this->getViewer();

    $dashboard_phid = $this->getDashboardPHID($config);

    // Reload the dashboard to attach panels, which we need for rendering.
    $dashboard = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($dashboard_phid))
      ->executeOne();
    if (!$dashboard) {
      return $this->newEmptyView(
        pht('Invalid Dashboard'),
        pht('This dashboard is invalid and could not be loaded.'));
    }

    if ($dashboard->isArchived()) {
      return $this->newEmptyView(
        pht('Archived Dashboard'),
        pht('This dashboard has been archived.'));
    }

    $engine = id(new PhabricatorDashboardRenderingEngine())
      ->setViewer($viewer)
      ->setDashboard($dashboard);

    return $engine->renderDashboard();
  }

  public function willGetMenuItemViewList(array $items) {
    $viewer = $this->getViewer();
    $dashboard_phids = array();
    foreach ($items as $item) {
      $dashboard_phids[] = $this->getDashboardPHID($item);
    }

    $dashboards = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withPHIDs($dashboard_phids)
      ->execute();

    $handles = $viewer->loadHandles($dashboard_phids);

    $dashboards = mpull($dashboards, null, 'getPHID');
    foreach ($items as $item) {
      $dashboard_phid = $this->getDashboardPHID($item);
      $dashboard = idx($dashboards, $dashboard_phid, null);

      $menu_item = $item->getMenuItem();

      $menu_item
        ->attachDashboard($dashboard)
        ->setDashboardHandle($handles[$dashboard_phid]);
    }
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $dashboard = $this->getDashboard();
    if (!$dashboard) {
      if ($this->getDashboardHandle()->getPolicyFiltered()) {
        return pht('Restricted Dashboard');
      }
      return pht('Invalid Dashboard');
    }

    if ($dashboard->isArchived()) {
      return pht('Archived Dashboard');
    }

    $default = $dashboard->getName();
    return $this->getNameFromConfig($config, $default);
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorDatasourceEditField())
        ->setKey(self::FIELD_DASHBOARD)
        ->setLabel(pht('Dashboard'))
        ->setIsRequired(true)
        ->setDatasource(new PhabricatorDashboardDatasource())
        ->setSingleValue($this->getDashboardPHID($config)),
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setValue($this->getNameFromConfig($config)),
    );
  }

  protected function newMenuItemViewList(
    PhabricatorProfileMenuItemConfiguration $config) {

    $is_disabled = true;
    $action_uri = null;

    $dashboard = $this->getDashboard();
    if ($dashboard) {
      if ($dashboard->isArchived()) {
        $icon = 'fa-ban';
        $name = $this->getDisplayName($config);
      } else {
        $icon = $dashboard->getIcon();
        $name = $this->getDisplayName($config);
        $is_disabled = false;
        $action_uri = $dashboard->getURI();
      }
    } else {
      $icon = 'fa-ban';
      if ($this->getDashboardHandle()->getPolicyFiltered()) {
        $name = pht('Restricted Dashboard');
      } else {
        $name = pht('Invalid Dashboard');
      }
    }

    $uri = $this->getItemViewURI($config);

    $item = $this->newItemView()
      ->setURI($uri)
      ->setName($name)
      ->setIcon($icon)
      ->setDisabled($is_disabled);

    if ($action_uri) {
      $item->newAction($action_uri);
    }

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

  private function getDashboardPHID(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $config->getMenuItemProperty('dashboardPHID');
  }

  private function getDashboardHandle() {
    return $this->dashboardHandle;
  }

  private function setDashboardHandle(PhabricatorObjectHandle $handle) {
    $this->dashboardHandle = $handle;
    return $this;
  }

}
