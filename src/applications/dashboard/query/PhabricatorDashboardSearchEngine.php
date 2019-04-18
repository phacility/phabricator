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
    return id(new PhabricatorDashboardQuery());
  }

  public function canUseInPanelContext() {
    return false;
  }

  protected function buildCustomSearchFields() {
    return array(
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

    if ($dashboards) {
      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(mpull($dashboards, 'getPHID'))
        ->withEdgeTypes(
          array(
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
          ));

      $edge_query->execute();
    }

    $list = id(new PHUIObjectItemListView())
      ->setViewer($viewer);

    foreach ($dashboards as $dashboard) {
      $item = id(new PHUIObjectItemView())
        ->setViewer($viewer)
        ->setObjectName($dashboard->getObjectName())
        ->setHeader($dashboard->getName())
        ->setHref($dashboard->getURI())
        ->setObject($dashboard);

      if ($dashboard->isArchived()) {
        $item->setDisabled(true);
        $bg_color = 'bg-grey';
      } else {
        $bg_color = 'bg-dark';
      }

      $icon = id(new PHUIIconView())
        ->setIcon($dashboard->getIcon())
        ->setBackground($bg_color);
      $item->setImageIcon($icon);
      $item->setEpoch($dashboard->getDateModified());

      $author_phid = $dashboard->getAuthorPHID();
      $author_name = $handles[$author_phid]->renderLink();
      $item->addByline(pht('Author: %s', $author_name));

      $phid = $dashboard->getPHID();
      $project_phids = $edge_query->getDestinationPHIDs(array($phid));
      $project_handles = $viewer->loadHandles($project_phids);

      $item->addAttribute(
        id(new PHUIHandleTagListView())
          ->setLimit(4)
          ->setNoDataString(pht('No Tags'))
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
