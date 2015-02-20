<?php

final class PhabricatorDashboardPanelSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Dashboard Panels');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDashboardApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter('status', $request->getStr('status'));
    $saved->setParameter('paneltype', $request->getStr('paneltype'));
    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorDashboardPanelQuery());

    $status = $saved->getParameter('status');
    switch ($status) {
      case 'active':
        $query->withArchived(false);
        break;
      case 'archived':
        $query->withArchived(true);
        break;
      default:
        break;
    }

    $paneltype = $saved->getParameter('paneltype');
    if ($paneltype) {
      $query->withPanelTypes(array($paneltype));
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $status = $saved_query->getParameter('status', '');
    $paneltype = $saved_query->getParameter('paneltype', '');

    $panel_types = PhabricatorDashboardPanelType::getAllPanelTypes();
    $panel_types = mpull($panel_types, 'getPanelTypeName', 'getPanelTypeKey');
    asort($panel_types);
    $panel_types = (array('' => pht('(All Types)')) + $panel_types);

    $form
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Status'))
          ->setName('status')
          ->setValue($status)
          ->setOptions(
            array(
              '' => pht('(All Panels)'),
              'active' => pht('Active Panels'),
              'archived' => pht('Archived Panels'),
            )))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Panel Type'))
          ->setName('paneltype')
          ->setValue($paneltype)
          ->setOptions($panel_types));
  }

  protected function getURI($path) {
    return '/dashboard/panel/'.$path;
  }

  protected function getBuiltinQueryNames() {
    return array(
      'active' => pht('Active Panels'),
      'all'    => pht('All Panels'),
    );
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'active':
        return $query->setParameter('status', 'active');
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $panels,
    PhabricatorSavedQuery $query,
    array $handles) {

    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($panels as $panel) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName($panel->getMonogram())
        ->setHeader($panel->getName())
        ->setHref('/'.$panel->getMonogram())
        ->setObject($panel);

      $impl = $panel->getImplementation();
      if ($impl) {
        $type_text = $impl->getPanelTypeName();
        $type_icon = 'none';
      } else {
        $type_text = nonempty($panel->getPanelType(), pht('Unknown Type'));
        $type_icon = 'fa-question';
      }

      $item->addIcon($type_icon, $type_text);

      if ($panel->getIsArchived()) {
        $item->setDisabled(true);
      }

      $list->addItem($item);
    }

    return $list;
  }

}
