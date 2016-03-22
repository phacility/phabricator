<?php

final class DiffusionRepositoryEditStagingController
  extends DiffusionRepositoryEditController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContextForEdit();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();


    if (!$repository->supportsStaging()) {
      return new Aphront404Response();
    }

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

    $v_area = $repository->getHumanReadableDetail('staging-uri');
    if ($request->isFormPost()) {
      $v_area = $request->getStr('area');

      $xactions = array();
      $template = id(new PhabricatorRepositoryTransaction());

      $type_encoding = PhabricatorRepositoryTransaction::TYPE_STAGING_URI;

      $xactions[] = id(clone $template)
        ->setTransactionType($type_encoding)
        ->setNewValue($v_area);

      id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->applyTransactions($repository, $xactions);

      return id(new AphrontRedirectResponse())->setURI($edit_uri);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Staging'));

    $title = pht('Edit Staging (%s)', $repository->getName());
    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-pencil');

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions(
        pht(
          "To make it easier to run integration tests and builds on code ".
          "under review, you can configure a **Staging Area**. When `arc` ".
          "creates a diff, it will push a copy of the changes to the ".
          "configured staging area with a corresponding tag.".
          "\n\n".
          "IMPORTANT: This feature is new, experimental, and not supported. ".
          "Use it at your own risk."))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Staging Area URI'))
          ->setName('area')
          ->setValue($v_area))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save'))
          ->addCancelButton($edit_uri));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Staging'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $form_box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
