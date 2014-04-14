<?php

final class ReleephBranchHistoryController extends ReleephBranchController {

  private $branchID;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->branchID = $data['branchID'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $branch = id(new ReleephBranchQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->branchID))
      ->executeOne();
    if (!$branch) {
      return new Aphront404Response();
    }
    $this->setBranch($branch);

    $xactions = id(new ReleephBranchTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($branch->getPHID()))
      ->execute();

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($branch->getPHID())
      ->setTransactions($xactions)
      ->setShouldTerminate(true);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('History'));

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
