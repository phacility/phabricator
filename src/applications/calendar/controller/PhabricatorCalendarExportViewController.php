<?php

final class PhabricatorCalendarExportViewController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $export = id(new PhabricatorCalendarExportQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$export) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Exports'),
      '/calendar/export/');
    $crumbs->addTextCrumb(pht('Export %d', $export->getID()));
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $export,
      new PhabricatorCalendarExportTransactionQuery());
    $timeline->setShouldTerminate(true);

    $header = $this->buildHeaderView($export);
    $curtain = $this->buildCurtain($export);
    $details = $this->buildPropertySection($export);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setMainColumn(
        array(
          $timeline,
        ))
      ->setCurtain($curtain)
      ->addPropertySection(pht('Details'), $details);

    $page_title = pht('Export %d %s', $export->getID(), $export->getName());

    return $this->newPage()
      ->setTitle($page_title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(array($export->getPHID()))
      ->appendChild($view);
  }

  private function buildHeaderView(
    PhabricatorCalendarExport $export) {
    $viewer = $this->getViewer();
    $id = $export->getID();

    if ($export->getIsDisabled()) {
      $icon = 'fa-ban';
      $color = 'red';
      $status = pht('Disabled');
    } else {
      $icon = 'fa-check';
      $color = 'bluegrey';
      $status = pht('Active');
    }

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($export->getName())
      ->setStatus($icon, $color, $status)
      ->setPolicyObject($export);

    return $header;
  }

  private function buildCurtain(PhabricatorCalendarExport $export) {
    $viewer = $this->getRequest()->getUser();
    $id = $export->getID();

    $curtain = $this->newCurtainView($export);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $export,
      PhabricatorPolicyCapability::CAN_EDIT);

    $ics_uri = $export->getICSURI();

    $edit_uri = "export/edit/{$id}/";
    $edit_uri = $this->getApplicationURI($edit_uri);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Export'))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setHref($edit_uri));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Export as .ics'))
        ->setIcon('fa-download')
        ->setHref($ics_uri));

    $disable_uri = "export/disable/{$id}/";
    $disable_uri = $this->getApplicationURI($disable_uri);
    if ($export->getIsDisabled()) {
      $disable_name = pht('Enable Export');
      $disable_icon = 'fa-check';
    } else {
      $disable_name = pht('Disable Export');
      $disable_icon = 'fa-ban';
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($disable_name)
        ->setIcon($disable_icon)
        ->setDisabled(!$can_edit)
        ->setWorkflow(true)
        ->setHref($disable_uri));

    return $curtain;
  }

  private function buildPropertySection(
    PhabricatorCalendarExport $export) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $mode = $export->getPolicyMode();

    $policy_icon = PhabricatorCalendarExport::getPolicyModeIcon($mode);
    $policy_name = PhabricatorCalendarExport::getPolicyModeName($mode);
    $policy_desc = PhabricatorCalendarExport::getPolicyModeDescription($mode);
    $policy_color = PhabricatorCalendarExport::getPolicyModeColor($mode);

    $policy_view = id(new PHUIStatusListView())
      ->addItem(
        id(new PHUIStatusItemView())
          ->setIcon($policy_icon, $policy_color)
          ->setTarget($policy_name)
          ->setNote($policy_desc));

    $properties->addProperty(pht('Mode'), $policy_view);

    $query_key = $export->getQueryKey();
    $query_link = phutil_tag(
      'a',
      array(
        'href' => $this->getApplicationURI("/query/{$query_key}/"),
      ),
      $query_key);
    $properties->addProperty(pht('Query'), $query_link);

    $ics_uri = $export->getICSURI();
    $ics_uri = PhabricatorEnv::getURI($ics_uri);

    if ($export->getIsDisabled()) {
      $ics_href = phutil_tag('em', array(), $ics_uri);
    } else {
      $ics_href = phutil_tag(
        'a',
        array(
          'href' => $ics_uri,
        ),
        $ics_uri);
    }

    $properties->addProperty(pht('ICS URI'), $ics_href);

    return $properties;
  }
}
