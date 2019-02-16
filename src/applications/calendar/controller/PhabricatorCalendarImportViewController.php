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

    $reload_uri = "import/reload/{$id}/";
    $reload_uri = $this->getApplicationURI($reload_uri);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Reload Import'))
        ->setIcon('fa-refresh')
        ->setDisabled(!$can_edit)
        ->setWorkflow(true)
        ->setHref($reload_uri));

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

    if ($can_edit) {
      $can_delete = $engine->canDeleteAnyEvents($viewer, $import);
    } else {
      $can_delete = false;
    }

    $delete_uri = "import/delete/{$id}/";
    $delete_uri = $this->getApplicationURI($delete_uri);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Delete Imported Events'))
        ->setIcon('fa-times')
        ->setDisabled(!$can_delete)
        ->setWorkflow(true)
        ->setHref($delete_uri));

    return $curtain;
  }

  private function buildPropertySection(
    PhabricatorCalendarImport $import) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $engine = $import->getEngine();

    $properties->addProperty(
      pht('Source Type'),
      $engine->getImportEngineTypeName());

    if ($import->getIsDisabled()) {
      $auto_updates = phutil_tag('em', array(), pht('Import Disabled'));
      $has_trigger = false;
    } else {
      $frequency = $import->getTriggerFrequency();
      $frequency_map = PhabricatorCalendarImport::getTriggerFrequencyMap();
      $frequency_names = ipull($frequency_map, 'name');
      $auto_updates = idx($frequency_names, $frequency, $frequency);

      if ($frequency == PhabricatorCalendarImport::FREQUENCY_ONCE) {
        $has_trigger = false;
        $auto_updates = phutil_tag('em', array(), $auto_updates);
      } else {
        $has_trigger = true;
      }
    }

    $properties->addProperty(
      pht('Automatic Updates'),
      $auto_updates);

    if ($has_trigger) {
      $trigger = id(new PhabricatorWorkerTriggerQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($import->getTriggerPHID()))
        ->needEvents(true)
        ->executeOne();

      if (!$trigger) {
        $next_trigger = phutil_tag('em', array(), pht('Invalid Trigger'));
      } else {
        $now = PhabricatorTime::getNow();
        $next_epoch = $trigger->getNextEventPrediction();
        $next_trigger = pht(
          '%s (%s)',
          phabricator_datetime($next_epoch, $viewer),
          phutil_format_relative_time($next_epoch - $now));
      }

      $properties->addProperty(
        pht('Next Update'),
        $next_trigger);
    }

    $engine->appendImportProperties(
      $viewer,
      $import,
      $properties);

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
      ->replaceQueryParam('importSourcePHID', $import->getPHID());

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
      ->replaceQueryParam('importSourcePHID', $import->getPHID())
      ->replaceQueryParam('display', 'list');

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
