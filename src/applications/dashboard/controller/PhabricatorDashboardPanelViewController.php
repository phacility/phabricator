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

    $header = $this->buildHeaderView($panel);
    $actions = $this->buildActionView($panel);
    $properties = $this->buildPropertyView($panel);
    $timeline = $this->buildTransactionTimeline(
      $panel,
      new PhabricatorDashboardPanelTransactionQuery());
    $timeline->setShouldTerminate(true);

    $properties->setActionList($actions);
    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $rendered_panel = id(new PhabricatorDashboardPanelRenderingEngine())
      ->setViewer($viewer)
      ->setPanel($panel)
      ->setParentPanelPHIDs(array())
      ->renderPanel();

    $view = id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE_LEFT)
      ->addMargin(PHUI::MARGIN_LARGE_RIGHT)
      ->addMargin(PHUI::MARGIN_LARGE_TOP)
      ->appendChild($rendered_panel);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $view,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildHeaderView(PhabricatorDashboardPanel $panel) {
    $viewer = $this->getRequest()->getUser();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($panel->getName())
      ->setPolicyObject($panel);

    if (!$panel->getIsArchived()) {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'red', pht('Archived'));
    }
    return $header;
  }

  private function buildActionView(PhabricatorDashboardPanel $panel) {
    $viewer = $this->getRequest()->getUser();
    $id = $panel->getID();

    $actions = id(new PhabricatorActionListView())
      ->setObjectURI('/'.$panel->getMonogram())
      ->setObject($panel)
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $panel,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
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

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName($archive_text)
        ->setIcon($archive_icon)
        ->setHref($this->getApplicationURI("panel/archive/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View Standalone'))
        ->setIcon('fa-eye')
        ->setHref($this->getApplicationURI("panel/render/{$id}/")));

    return $actions;
  }

  private function buildPropertyView(PhabricatorDashboardPanel $panel) {
    $viewer = $this->getRequest()->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($panel);

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

    return $properties;
  }

}
