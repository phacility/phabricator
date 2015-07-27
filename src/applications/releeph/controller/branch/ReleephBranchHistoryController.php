<?php

final class ReleephBranchHistoryController extends ReleephBranchController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('branchID');

    $branch = id(new ReleephBranchQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
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
