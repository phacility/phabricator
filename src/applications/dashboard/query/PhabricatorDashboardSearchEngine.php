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
    return array(
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('name')
        ->setDescription(pht('Search for dashboards by name substring.')),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Authored By'))
        ->setKey('authorPHIDs')
        ->setDatasource(new PhabricatorPeopleUserFunctionDatasource()),
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('statuses')
        ->setLabel(pht('Status'))
        ->setOptions(PhabricatorDashboard::getStatusNameMap()),
    );
  }

  protected function getURI($path) {
    return '/dashboard/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['authored'] = pht('Authored');
    }

    $names['open'] = pht('Active Dashboards');
    $names['all'] = pht('All Dashboards');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);
    $viewer = $this->requireViewer();

    switch ($query_key) {
      case 'all':
        return $query;
      case 'authored':
        return $query->setParameter(
          'authorPHIDs',
          array(
            $viewer->getPHID(),
          ));
      case 'open':
        return $query->setParameter(
          'statuses',
          array(
            PhabricatorDashboard::STATUS_ACTIVE,
          ));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['statuses']) {
      $query->withStatuses($map['statuses']);
    }

    if ($map['authorPHIDs']) {
      $query->withAuthorPHIDs($map['authorPHIDs']);
    }

    if ($map['name'] !== null) {
      $query->withNameNgrams($map['name']);
    }

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

      if ($dashboard->isArchived()) {
        $item->setDisabled(true);
      }

      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $dashboard,
        PhabricatorPolicyCapability::CAN_EDIT);

      $href_view = $this->getApplicationURI("manage/{$id}/");
      $item->addAction(
        id(new PHUIListItemView())
          ->setName(pht('Manage'))
          ->setIcon('fa-th')
          ->setHref($href_view));

      $href_edit = $this->getApplicationURI("edit/{$id}/");
      $item->addAction(
        id(new PHUIListItemView())
          ->setName(pht('Edit'))
          ->setIcon('fa-pencil')
          ->setHref($href_edit)
          ->setDisabled(!$can_edit));

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No dashboards found.'));

    return $result;
  }

  protected function getNewUserBody() {
    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Create a Dashboard'))
      ->setHref('/dashboard/create/')
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon($icon)
      ->setTitle(pht('Welcome to %s', $app_name))
      ->setDescription(
        pht('Customize your homepage with different panels and '.
            'search queries.'))
      ->addAction($create_button);

      return $view;
  }

}
