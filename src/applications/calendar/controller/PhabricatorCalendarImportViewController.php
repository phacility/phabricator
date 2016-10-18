<?php

final class PhabricatorCalendarImportViewController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $import = id(new PhabricatorCalendarImportQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$import) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Imports'),
      '/calendar/import/');
    $crumbs->addTextCrumb(pht('Import %d', $import->getID()));
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $import,
      new PhabricatorCalendarImportTransactionQuery());
    $timeline->setShouldTerminate(true);

    $header = $this->buildHeaderView($import);
    $curtain = $this->buildCurtain($import);
    $details = $this->buildPropertySection($import);

    $log_messages = $this->buildLogMessages($import);
    $imported_events = $this->buildImportedEvents($import);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setMainColumn(
        array(
          $log_messages,
          $imported_events,
          $timeline,
        ))
      ->setCurtain($curtain)
      ->addPropertySection(pht('Details'), $details);

    $page_title = pht(
      'Import %d %s',
      $import->getID(),
      $import->getDisplayName());

    return $this->newPage()
      ->setTitle($page_title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(array($import->getPHID()))
      ->appendChild($view);
  }

  private function buildHeaderView(
    PhabricatorCalendarImport $import) {
    $viewer = $this->getViewer();
    $id = $import->getID();

    if ($import->getIsDisabled()) {
      $icon = 'fa-ban';
      $color = 'red';
      $status = pht('Disabled');
    } else {
      $icon = 'fa-check';
      $color = 'bluegrey';
      $status = pht('Active');
    }

    $header = id(new PHUIHeaderView())
      ->setViewer($viewer)
      ->setHeader($import->getDisplayName())
      ->setStatus($icon, $color, $status)
      ->setPolicyObject($import);

    return $header;
  }

  private function buildCurtain(PhabricatorCalendarImport $import) {
    $viewer = $this->getViewer();
    $id = $import->getID();

    $curtain = $this->newCurtainView($import);
    $engine = $import->getEngine();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $import,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = "import/edit/{$id}/";
    $edit_uri = $this->getApplicationURI($edit_uri);

    $can_disable = ($can_edit && $engine->canDisable($viewer, $import));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Import'))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setHref($edit_uri));

    $disable_uri = "import/disable/{$id}/";
    $disable_uri = $this->getApplicationURI($disable_uri);
    if ($import->getIsDisabled()) {
      $disable_name = pht('Enable Import');
      $disable_icon = 'fa-check';
    } else {
      $disable_name = pht('Disable Import');
      $disable_icon = 'fa-ban';
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($disable_name)
        ->setIcon($disable_icon)
        ->setDisabled(!$can_disable)
        ->setWorkflow(true)
        ->setHref($disable_uri));

    return $curtain;
  }

  private function buildPropertySection(
    PhabricatorCalendarImport $import) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    return $properties;
  }

  private function buildLogMessages(PhabricatorCalendarImport $import) {
    $viewer = $this->getViewer();

    $logs = id(new PhabricatorCalendarImportLogQuery())
      ->setViewer($viewer)
      ->withImportPHIDs(array($import->getPHID()))
      ->setLimit(25)
      ->execute();

    $logs_view = id(new PhabricatorCalendarImportLogView())
      ->setViewer($viewer)
      ->setLogs($logs);

    $all_uri = $this->getApplicationURI('import/log/');
    $all_uri = (string)id(new PhutilURI($all_uri))
      ->setQueryParam('importSourcePHID', $import->getPHID());

    $all_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('View All'))
      ->setIcon('fa-search')
      ->setHref($all_uri);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Log Messages'))
      ->addActionLink($all_button);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($logs_view);
  }

  private function buildImportedEvents(PhabricatorCalendarImport $import) {
    $viewer = $this->getViewer();

    $engine = id(new PhabricatorCalendarEventSearchEngine())
      ->setViewer($viewer);

    $saved = $engine->newSavedQuery()
      ->setParameter('importSourcePHIDs', array($import->getPHID()));

    $pager = $engine->newPagerForSavedQuery($saved);
    $pager->setPageSize(25);

    $query = $engine->buildQueryFromSavedQuery($saved);

    $results = $engine->executeQuery($query, $pager);
    $view = $engine->renderResults($results, $saved);
    $list = $view->getObjectList();
    $list->setNoDataString(pht('No imported events.'));

    $all_uri = $this->getApplicationURI();
    $all_uri = (string)id(new PhutilURI($all_uri))
      ->setQueryParam('importSourcePHID', $import->getPHID())
      ->setQueryParam('display', 'list');

    $all_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('View All'))
      ->setIcon('fa-search')
      ->setHref($all_uri);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Imported Events'))
      ->addActionLink($all_button);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($list);
  }

}
