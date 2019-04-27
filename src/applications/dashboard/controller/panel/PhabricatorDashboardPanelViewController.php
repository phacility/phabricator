<?php

final class PhabricatorDashboardPanelViewController
  extends PhabricatorDashboardController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $panel = id(new PhabricatorDashboardPanelQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$panel) {
      return new Aphront404Response();
    }

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $panel,
      PhabricatorPolicyCapability::CAN_EDIT);

    $title = $panel->getMonogram().' '.$panel->getName();
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Panels'),
      $this->getApplicationURI('panel/'));
    $crumbs->addTextCrumb($panel->getMonogram());
    $crumbs->setBorder(true);

    $header = $this->buildHeaderView($panel);
    $curtain = $this->buildCurtainView($panel);

    $usage_box = $this->newUsageView($panel);

    $timeline = $this->buildTransactionTimeline(
      $panel,
      new PhabricatorDashboardPanelTransactionQuery());
    $timeline->setShouldTerminate(true);

    $rendered_panel = id(new PhabricatorDashboardPanelRenderingEngine())
      ->setViewer($viewer)
      ->setPanel($panel)
      ->setContextObject($panel)
      ->setPanelPHID($panel->getPHID())
      ->setParentPanelPHIDs(array())
      ->setEditMode(true)
      ->renderPanel();

    $preview = id(new PHUIBoxView())
      ->addClass('dashboard-preview-box')
      ->appendChild($rendered_panel);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $rendered_panel,
        $usage_box,
        $timeline,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildHeaderView(PhabricatorDashboardPanel $panel) {
    $viewer = $this->getViewer();
    $id = $panel->getID();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($panel->getName())
      ->setPolicyObject($panel)
      ->setHeaderIcon('fa-window-maximize');

    if (!$panel->getIsArchived()) {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'red', pht('Archived'));
    }
    return $header;
  }

  private function buildCurtainView(PhabricatorDashboardPanel $panel) {
    $viewer = $this->getViewer();
    $id = $panel->getID();

    $curtain = $this->newCurtainView($panel);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $panel,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Panel'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("panel/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if (!$panel->getIsArchived()) {
      $archive_text = pht('Archive Panel');
      $archive_icon = 'fa-ban';
    } else {
      $archive_text = pht('Activate Panel');
      $archive_icon = 'fa-check';
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($archive_text)
        ->setIcon($archive_icon)
        ->setHref($this->getApplicationURI("panel/archive/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $curtain;
  }

  private function newUsageView(PhabricatorDashboardPanel $panel) {
    $viewer = $this->getViewer();

    $object_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $panel->getPHID(),
      PhabricatorDashboardPanelUsedByObjectEdgeType::EDGECONST);

    if ($object_phids) {
      $handles = $viewer->loadHandles($object_phids);
    } else {
      $handles = array();
    }

    $rows = array();
    foreach ($object_phids as $object_phid) {
      $handle = $handles[$object_phid];

      $icon = $handle->getIcon();

      $rows[] = array(
        id(new PHUIIconView())->setIcon($icon),
        $handle->getTypeName(),
        $handle->renderLink(),
      );
    }

    $usage_table = id(new AphrontTableView($rows))
      ->setNoDataString(
        pht(
          'This panel is not used on any dashboard or inside any other '.
          'panel container.'))
      ->setColumnClasses(
        array(
          'center',
          '',
          'pri wide',
        ));

    $header_view = id(new PHUIHeaderView())
      ->setHeader(pht('Panel Used By'));

    $usage_box = id(new PHUIObjectBoxView())
      ->setTable($usage_table)
      ->setHeader($header_view);

    return $usage_box;
  }
}
