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

    $title = $panel->getMonogram().' '.$panel->getName();
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Panels'),
      $this->getApplicationURI('panel/'));
    $crumbs->addTextCrumb($panel->getMonogram());
    $crumbs->setBorder(true);

    $header = $this->buildHeaderView($panel);
    $curtain = $this->buildCurtainView($panel);
    $properties = $this->buildPropertyView($panel);

    $timeline = $this->buildTransactionTimeline(
      $panel,
      new PhabricatorDashboardPanelTransactionQuery());

    $rendered_panel = id(new PhabricatorDashboardPanelRenderingEngine())
      ->setViewer($viewer)
      ->setPanel($panel)
      ->setPanelPHID($panel->getPHID())
      ->setParentPanelPHIDs(array())
      ->renderPanel();

    $preview = id(new PHUIBoxView())
      ->addClass('dashboard-preview-box')
      ->appendChild($rendered_panel);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $properties,
        $timeline,
      ))
      ->setFooter($rendered_panel);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildHeaderView(PhabricatorDashboardPanel $panel) {
    $viewer = $this->getViewer();
    $id = $panel->getID();

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('View Panel'))
      ->setIcon('fa-columns')
      ->setHref($this->getApplicationURI("panel/render/{$id}/"));

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($panel->getName())
      ->setPolicyObject($panel)
      ->setHeaderIcon('fa-columns')
      ->addActionLink($button);

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

  private function buildPropertyView(PhabricatorDashboardPanel $panel) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $panel);

    $panel_type = $panel->getImplementation();
    if ($panel_type) {
      $type_name = $panel_type->getPanelTypeName();
    } else {
      $type_name = phutil_tag(
        'em',
        array(),
        nonempty($panel->getPanelType(), pht('null')));
    }

    $properties->addProperty(
      pht('Panel Type'),
      $type_name);

    $properties->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);

    $dashboard_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $panel->getPHID(),
      PhabricatorDashboardPanelHasDashboardEdgeType::EDGECONST);

    $does_not_appear = pht(
      'This panel does not appear on any dashboards.');

    $properties->addProperty(
      pht('Appears On'),
      $dashboard_phids
        ? $viewer->renderHandleList($dashboard_phids)
        : phutil_tag('em', array(), $does_not_appear));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addPropertyList($properties);
  }

}
