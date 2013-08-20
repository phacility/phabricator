<?php

final class ReleephProjectHistoryController extends ReleephProjectController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['projectID'];
    parent::willProcessRequest($data);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $project = id(new ReleephProjectQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $xactions = id(new ReleephProjectTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($project->getPHID()))
      ->execute();

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($project->getPHID())
      ->setTransactions($xactions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('History')));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $timeline,
      ),
      array(
        'title' => pht('Project History'),
        'device' => true,
      ));
  }

}
