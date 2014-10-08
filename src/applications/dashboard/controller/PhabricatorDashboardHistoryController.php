<?php

final class PhabricatorDashboardHistoryController
  extends PhabricatorDashboardController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $id = $this->id;
    $dashboard_view_uri = $this->getApplicationURI('view/'.$id.'/');
    $dashboard_manage_uri = $this->getApplicationURI('manage/'.$id.'/');

    $dashboard = id(new PhabricatorDashboardQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$dashboard) {
      return new Aphront404Response();
    }

    $title = $dashboard->getName();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Dashboard %d', $dashboard->getID()),
      $dashboard_view_uri);
    $crumbs->addTextCrumb(
      pht('Manage'),
      $dashboard_manage_uri);
    $crumbs->addTextCrumb(pht('History'));

    $timeline = $this->buildTransactions($dashboard);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildTransactions(PhabricatorDashboard $dashboard) {
    $viewer = $this->getRequest()->getUser();

    $xactions = id(new PhabricatorDashboardTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($dashboard->getPHID()))
      ->execute();

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setShouldTerminate(true)
      ->setObjectPHID($dashboard->getPHID())
      ->setTransactions($xactions);

    return $timeline;
  }

}
