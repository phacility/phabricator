<?php

final class ReleephBranchHistoryController extends ReleephProjectController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['branchID'];
    parent::willProcessRequest($data);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $branch = id(new ReleephBranchQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$branch) {
      return new Aphront404Response();
    }

    $xactions = id(new ReleephBranchTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($branch->getPHID()))
      ->execute();

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($branch->getPHID())
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
        'title' => pht('Branch History'),
        'device' => true,
      ));
  }

}
