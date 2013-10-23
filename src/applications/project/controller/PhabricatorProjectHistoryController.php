<?php

final class PhabricatorProjectHistoryController
  extends PhabricatorProjectController {

  private $id;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $this->id;

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $xactions = id(new PhabricatorProjectTransactionQuery())
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
        ->setName($project->getName())
        ->setHref($this->getApplicationURI("view/{$id}/")));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('History')));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $timeline,
      ),
      array(
        'title' => $project->getName(),
        'device' => true,
      ));
  }

}
