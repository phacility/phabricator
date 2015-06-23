<?php

final class PhabricatorDaemonBulkJobViewController
  extends PhabricatorDaemonController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $job = id(new PhabricatorWorkerBulkJobQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$job) {
      return new Aphront404Response();
    }

    $title = pht('Bulk Job %d', $job->getID());

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Bulk Jobs'), '/daemon/bulk/');
    $crumbs->addTextCrumb($title);

    $properties = $this->renderProperties($job);
    $actions = $this->renderActions($job);
    $properties->setActionList($actions);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->addPropertyList($properties);

    $timeline = $this->buildTransactionTimeline(
      $job,
      new PhabricatorWorkerBulkJobTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function renderProperties(PhabricatorWorkerBulkJob $job) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($job);

    $view->addProperty(
      pht('Author'),
      $viewer->renderHandle($job->getAuthorPHID()));

    $view->addProperty(pht('Status'), $job->getStatusName());

    return $view;
  }

  private function renderActions(PhabricatorWorkerBulkJob $job) {
    $viewer = $this->getViewer();

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($job);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setHref($job->getDoneURI())
        ->setIcon('fa-arrow-circle-o-right')
        ->setName(pht('Continue')));

    return $actions;
  }

}
