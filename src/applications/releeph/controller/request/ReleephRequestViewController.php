<?php

final class ReleephRequestViewController extends ReleephProjectController {

  public function processRequest() {
    $request = $this->getRequest();

    $uri_path = $request->getRequestURI()->getPath();
    $legacy_prefix = '/releeph/request/';
    if (strncmp($uri_path, $legacy_prefix, strlen($legacy_prefix)) === 0) {
      return id(new AphrontRedirectResponse())
        ->setURI('/RQ'.$this->getReleephRequest()->getID());
    }

    $releeph_request = $this->getReleephRequest();
    $releeph_branch  = $this->getReleephBranch();
    $releeph_project = $this->getReleephProject();

    $releeph_branch->populateReleephRequestHandles(
      $request->getUser(), array($releeph_request));

    $rq_view =
      id(new ReleephRequestHeaderListView())
        ->setReleephProject($releeph_project)
        ->setReleephBranch($releeph_branch)
        ->setReleephRequests(array($releeph_request))
        ->setUser($request->getUser())
        ->setAphrontRequest($this->getRequest())
        ->setReloadOnStateChange(true)
        ->setOriginType('request');

    $user = $request->getUser();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);

    $xactions = id(new ReleephRequestTransactionQuery())
      ->setViewer($user)
      ->withObjectPHIDs(array($releeph_request->getPHID()))
      ->execute();

    foreach ($xactions as $xaction) {
      if ($xaction->getComment()) {
        $engine->addObject(
          $xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($request->getUser())
      ->setObjectPHID($releeph_request->getPHID())
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    $add_comment_header = pht('Plea or yield');

    $draft = PhabricatorDraft::newFromUserAndKey(
      $user,
      $releeph_request->getPHID());

    $title = hsprintf("RQ%d: %s",
      $releeph_request->getID(),
      $releeph_request->getSummaryForDisplay());

    $add_comment_form = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($user)
      ->setObjectPHID($releeph_request->getPHID())
      ->setDraft($draft)
      ->setHeaderText($add_comment_header)
      ->setAction($this->getApplicationURI(
        '/request/comment/'.$releeph_request->getID().'/'))
      ->setSubmitButtonName('Comment');

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($releeph_project->getName(), $releeph_project->getURI())
      ->addTextCrumb(
        $releeph_branch->getDisplayNameWithDetail(),
        $releeph_branch->getURI())
      ->addTextCrumb(
        'RQ'.$releeph_request->getID(),
        '/RQ'.$releeph_request->getID());

    return $this->buildStandardPageResponse(
      array(
        $crumbs,
        array(
          $rq_view,
          $timeline,
          $add_comment_form,
        )
      ),
      array(
        'title' => $title
      ));
  }
}
