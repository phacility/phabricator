<?php

final class ReleephRequestViewController
  extends ReleephBranchController {

  private $requestID;

  public function willProcessRequest(array $data) {
    $this->requestID = $data['requestID'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $pull = id(new ReleephRequestQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->requestID))
      ->executeOne();
    if (!$pull) {
      return new Aphront404Response();
    }
    $this->setBranch($pull->getBranch());

    // Redirect older URIs to new "Y" URIs.
    // TODO: Get rid of this eventually.
    $actual_path = $request->getRequestURI()->getPath();
    $expect_path = '/'.$pull->getMonogram();
    if ($actual_path != $expect_path) {
      return id(new AphrontRedirectResponse())->setURI($expect_path);
    }

    // TODO: Break this 1:1 stuff?
    $branch = $pull->getBranch();

    // TODO: Very gross.
    $branch->populateReleephRequestHandles(
      $viewer,
      array($pull));

    $rq_view = id(new ReleephRequestHeaderListView())
        ->setReleephProject($branch->getProduct())
        ->setReleephBranch($branch)
        ->setReleephRequests(array($pull))
        ->setUser($viewer)
        ->setAphrontRequest($request)
        ->setReloadOnStateChange(true);

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);

    $xactions = id(new ReleephRequestTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($pull->getPHID()))
      ->execute();

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($request->getUser())
      ->setObjectPHID($pull->getPHID())
      ->setTransactions($xactions);

    $add_comment_header = pht('Plea or Yield');

    $draft = PhabricatorDraft::newFromUserAndKey(
      $viewer,
      $pull->getPHID());

    $title = hsprintf(
      "%s %s",
      $pull->getMonogram(),
      $pull->getSummaryForDisplay());

    $add_comment_form = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($pull->getPHID())
      ->setDraft($draft)
      ->setHeaderText($add_comment_header)
      ->setAction($this->getApplicationURI(
        '/request/comment/'.$pull->getID().'/'))
      ->setSubmitButtonName(pht('Comment'));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($pull->getMonogram(), '/'.$pull->getMonogram());

    return $this->buildStandardPageResponse(
      array(
        $crumbs,
        $rq_view,
        $timeline,
        $add_comment_form,
      ),
      array(
        'title' => $title
      ));
  }
}
