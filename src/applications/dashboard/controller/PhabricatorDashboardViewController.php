<?php

final class PhabricatorDashboardViewController
  extends PhabricatorDashboardController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $dashboard = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->needPanels(true)
      ->executeOne();
    if (!$dashboard) {
      return new Aphront404Response();
    }

    $title = $dashboard->getName();
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Dashboard %d', $dashboard->getID()));

    $header = $this->buildHeaderView($dashboard);
    $actions = $this->buildActionView($dashboard);
    $properties = $this->buildPropertyView($dashboard);
    $timeline = $this->buildTransactions($dashboard);

    $properties->setActionList($actions);
    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $rendered_dashboard = id(new PhabricatorDashboardRenderingEngine())
      ->setViewer($viewer)
      ->setDashboard($dashboard)
      ->renderDashboard();

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
        $rendered_dashboard,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildHeaderView(PhabricatorDashboard $dashboard) {
    $viewer = $this->getRequest()->getUser();

    return id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($dashboard->getName())
      ->setPolicyObject($dashboard);
  }

  private function buildActionView(PhabricatorDashboard $dashboard) {
    $viewer = $this->getRequest()->getUser();
    $id = $dashboard->getID();

    $actions = id(new PhabricatorActionListView())
      ->setObjectURI($this->getApplicationURI('view/'.$dashboard->getID().'/'))
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $dashboard,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Dashboard'))
        ->setIcon('edit')
        ->setHref($this->getApplicationURI("edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Add Panel'))
        ->setIcon('new')
        ->setHref($this->getApplicationURI("addpanel/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $actions;
  }

  private function buildPropertyView(PhabricatorDashboard $dashboard) {
    $viewer = $this->getRequest()->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($dashboard);

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $dashboard);

    $properties->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);

    $panel_phids = $dashboard->getPanelPHIDs();
    $this->loadHandles($panel_phids);

    $properties->addProperty(
      pht('Panels'),
      $this->renderHandlesForPHIDs($panel_phids));

    return $properties;
  }

  private function buildTransactions(PhabricatorDashboard $dashboard) {
    $viewer = $this->getRequest()->getUser();

    $xactions = id(new PhabricatorDashboardTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($dashboard->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($dashboard->getPHID())
      ->setTransactions($xactions);

    return $timeline;
  }

}
