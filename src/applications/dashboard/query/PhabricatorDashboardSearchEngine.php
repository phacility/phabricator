<?php

final class PhabricatorDashboardSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Dashboards');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDashboardApplication';
  }

  public function newQuery() {
    return id(new PhabricatorDashboardQuery())
      ->needProjects(true);
  }

  protected function buildCustomSearchFields() {
    return array();
  }

  protected function getURI($path) {
    return '/dashboard/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'all' => pht('All Dashboards'),
    );
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();
    return $query;
  }

  protected function renderResultList(
    array $dashboards,
    PhabricatorSavedQuery $query,
    array $handles) {

    $dashboards = mpull($dashboards, null, 'getPHID');
    $viewer = $this->requireViewer();

    if ($dashboards) {
      $installs = id(new PhabricatorDashboardInstall())
        ->loadAllWhere(
          'objectPHID IN (%Ls) AND dashboardPHID IN (%Ls)',
          array(
            PhabricatorHomeApplication::DASHBOARD_DEFAULT,
            $viewer->getPHID(),
          ),
          array_keys($dashboards));
      $installs = mpull($installs, null, 'getDashboardPHID');
    } else {
      $installs = array();
    }

    $proj_phids = array();
    foreach ($dashboards as $dashboard) {
      foreach ($dashboard->getProjectPHIDs() as $project_phid) {
        $proj_phids[] = $project_phid;
      }
    }

    $proj_handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($proj_phids)
      ->execute();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    $list->initBehavior('phabricator-tooltips', array());
    $list->requireResource('aphront-tooltip-css');

    foreach ($dashboards as $dashboard_phid => $dashboard) {
      $id = $dashboard->getID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Dashboard %d', $id))
        ->setHeader($dashboard->getName())
        ->setHref($this->getApplicationURI("view/{$id}/"))
        ->setObject($dashboard);

      if (isset($installs[$dashboard_phid])) {
        $install = $installs[$dashboard_phid];
        if ($install->getObjectPHID() == $viewer->getPHID()) {
          $attrs = array(
            'tip' => pht(
              'This dashboard is installed to your personal homepage.'),
          );
          $item->addIcon('fa-user', pht('Installed'), $attrs);
        } else {
          $attrs = array(
            'tip' => pht(
              'This dashboard is the default homepage for all users.'),
          );
          $item->addIcon('fa-globe', pht('Installed'), $attrs);
        }
      }

      $project_handles = array_select_keys(
        $proj_handles,
        $dashboard->getProjectPHIDs());

      $item->addAttribute(
        id(new PHUIHandleTagListView())
          ->setLimit(4)
          ->setNoDataString(pht('No Projects'))
          ->setSlim(true)
          ->setHandles($project_handles));

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No dashboards found.'));

    return $result;
  }

}
