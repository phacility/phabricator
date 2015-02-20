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
    $crumbs->setBorder(true);
    $crumbs->addTextCrumb(
      pht('Dashboard %d', $dashboard->getID()),
      $dashboard_view_uri);
    $crumbs->addTextCrumb(
      pht('Manage'),
      $dashboard_manage_uri);
    $crumbs->addTextCrumb(pht('History'));

    $timeline = $this->buildTransactionTimeline(
      $dashboard,
      new PhabricatorDashboardTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

}
