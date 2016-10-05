<?php

final class PhabricatorCalendarExportSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Calendar Exports');
  }

  public function getApplicationClassName() {
    return 'PhabricatorCalendarApplication';
  }

  public function newQuery() {
    $viewer = $this->requireViewer();

    return id(new PhabricatorCalendarExportQuery())
      ->withAuthorPHIDs(array($viewer->getPHID()));
  }

  protected function buildCustomSearchFields() {
    return array();
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    return $query;
  }

  protected function getURI($path) {
    return '/calendar/export/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Exports'),
    );

    return $names;
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

  protected function renderResultList(
    array $exports,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($exports, 'PhabricatorCalendarExport');
    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    foreach ($exports as $export) {
      $item = id(new PHUIObjectItemView())
        ->setViewer($viewer)
        ->setObjectName(pht('Export %d', $export->getID()))
        ->setHeader($export->getName())
        ->setHref($export->getURI());

      if ($export->getIsDisabled()) {
        $item->setDisabled(true);
      }

      $mode = $export->getPolicyMode();
      $policy_icon = PhabricatorCalendarExport::getPolicyModeIcon($mode);
      $policy_name = PhabricatorCalendarExport::getPolicyModeName($mode);
      $policy_color = PhabricatorCalendarExport::getPolicyModeColor($mode);

      $item->addIcon(
        "{$policy_icon} {$policy_color}",
        $policy_name);

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No exports found.'));

    return $result;
  }

  protected function getNewUserBody() {
    $doc_name = 'Calendar User Guide: Exporting Events';
    $doc_href = PhabricatorEnv::getDoclink($doc_name);

    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-book white')
      ->setText($doc_name)
      ->setHref($doc_href)
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon('fa-download')
      ->setTitle(pht('No Exports Configured'))
      ->setDescription(
        pht(
          'You have not set up any events for export from Calendar yet. '.
          'See the documentation for instructions on how to get started.'))
      ->addAction($create_button);

    return $view;
  }

}
