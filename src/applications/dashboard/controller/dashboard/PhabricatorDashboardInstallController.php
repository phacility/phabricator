<?php

final class PhabricatorDashboardInstallController
  extends PhabricatorDashboardController {

  private $dashboard;

  public function setDashboard(PhabricatorDashboard $dashboard) {
    $this->dashboard = $dashboard;
    return $this;
  }

  public function getDashboard() {
    return $this->dashboard;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $dashboard = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$dashboard) {
      return new Aphront404Response();
    }

    $this->setDashboard($dashboard);
    $cancel_uri = $dashboard->getURI();

    $workflow_key = $request->getURIData('workflowKey');

    $workflows = PhabricatorDashboardInstallWorkflow::getAllWorkflows();
    if (!isset($workflows[$workflow_key])) {
      return $this->newWorkflowDialog($dashboard, $workflows);
    }

    return id(clone $workflows[$workflow_key])
      ->setRequest($request)
      ->setViewer($viewer)
      ->setDashboard($dashboard)
      ->setMode($request->getURIData('modeKey'))
      ->handleRequest($request);
  }

  private function newWorkflowDialog(
    PhabricatorDashboard $dashboard,
    array $workflows) {
    $viewer = $this->getViewer();
    $cancel_uri = $dashboard->getURI();

    $menu = id(new PHUIObjectItemListView())
      ->setViewer($viewer)
      ->setFlush(true)
      ->setBig(true);

    foreach ($workflows as $key => $workflow) {
      $item = $workflow->getWorkflowMenuItem();

      $item_href = urisprintf('install/%d/%s/', $dashboard->getID(), $key);
      $item_href = $this->getApplicationURI($item_href);
      $item->setHref($item_href);

      $menu->addItem($item);
    }

    return $this->newDialog()
      ->setTitle(pht('Add Dashboard to Menu'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($menu)
      ->addCancelButton($cancel_uri);
  }

}
