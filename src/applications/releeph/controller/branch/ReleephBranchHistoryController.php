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

    $timeline = $this->buildTransactionTimeline(
      $branch,
      new ReleephBranchTransactionQuery());
    $timeline
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
      ));
  }

}
