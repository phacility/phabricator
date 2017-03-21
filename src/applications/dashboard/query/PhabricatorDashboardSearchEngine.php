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
      ->needPanels(true);
  }

  public function canUseInPanelContext() {
    return false;
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
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('editable')
        ->setLabel(pht('Editable'))
        ->setOptions(array('editable' => null)),
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

    if ($map['editable'] !== null) {
      $query->withCanEdit($map['editable']);
    }

    return $query;
  }

  protected function renderResultList(
    array $dashboards,
    PhabricatorSavedQuery $query,
    array $handles) {

    $viewer = $this->requireViewer();

    $phids = array();
    foreach ($dashboards as $dashboard) {
      $author_phid = $dashboard->getAuthorPHID();
      if ($author_phid) {
        $phids[] = $author_phid;
      }
    }

    $handles = $viewer->loadHandles($phids);

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($dashboards as $dashboard) {
      $id = $dashboard->getID();

      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setHeader($dashboard->getName())
        ->setHref($this->getApplicationURI("view/{$id}/"))
        ->setObject($dashboard);

      $bg_color = 'bg-dark';
      if ($dashboard->isArchived()) {
        $item->setDisabled(true);
        $bg_color = 'bg-grey';
      }

      $panels = $dashboard->getPanels();
      foreach ($panels as $panel) {
        $item->addAttribute($panel->getName());
      }

      if (empty($panels)) {
        $empty = phutil_tag('em', array(), pht('No panels.'));
        $item->addAttribute($empty);
      }

      $icon = id(new PHUIIconView())
        ->setIcon($dashboard->getIcon())
        ->setBackground($bg_color);
      $item->setImageIcon($icon);
      $item->setEpoch($dashboard->getDateModified());

      $author_phid = $dashboard->getAuthorPHID();
      $author_name = $handles[$author_phid]->renderLink();
      $item->addByline(pht('Author: %s', $author_name));

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
