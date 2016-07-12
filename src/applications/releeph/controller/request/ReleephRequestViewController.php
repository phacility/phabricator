<?php

final class ReleephRequestViewController
  extends ReleephBranchController {

  public function handleRequest(AphrontRequest $request) {
    $id = $request->getURIData('requestID');
    $viewer = $request->getViewer();

    $pull = id(new ReleephRequestQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
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

    $field_list = PhabricatorCustomField::getObjectFields(
      $pull,
      PhabricatorCustomField::ROLE_VIEW);

    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($pull);

    // TODO: This should be more modern and general.
    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);
    foreach ($field_list->getFields() as $field) {
      if ($field->shouldMarkup()) {
        $field->setMarkupEngine($engine);
      }
    }
    $engine->process();

    $pull_box = id(new ReleephRequestView())
      ->setUser($viewer)
      ->setCustomFields($field_list)
      ->setPullRequest($pull);

    $timeline = $this->buildTransactionTimeline(
      $pull,
      new ReleephRequestTransactionQuery());

    $add_comment_header = pht('Plea or Yield');

    $draft = PhabricatorDraft::newFromUserAndKey(
      $viewer,
      $pull->getPHID());

    $title = hsprintf(
      '%s %s',
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
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-flag-checkered');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $pull_box,
        $timeline,
        $add_comment_form,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }


}
