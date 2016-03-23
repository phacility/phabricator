<?php

final class DiffusionRepositoryEditActionsController
  extends DiffusionRepositoryEditController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContextForEdit();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

    // NOTE: We're inverting these here, because the storage is silly.
    $v_notify = !$repository->getHumanReadableDetail('herald-disabled');
    $v_autoclose = !$repository->getHumanReadableDetail('disable-autoclose');

    if ($request->isFormPost()) {
      $v_notify = $request->getBool('notify');
      $v_autoclose = $request->getBool('autoclose');

      $xactions = array();
      $template = id(new PhabricatorRepositoryTransaction());

      $type_notify = PhabricatorRepositoryTransaction::TYPE_NOTIFY;
      $type_autoclose = PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE;

      $xactions[] = id(clone $template)
        ->setTransactionType($type_notify)
        ->setNewValue($v_notify);

      $xactions[] = id(clone $template)
        ->setTransactionType($type_autoclose)
        ->setNewValue($v_autoclose);

      id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->applyTransactions($repository, $xactions);

      return id(new AphrontRedirectResponse())->setURI($edit_uri);
    }

    $content = array();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Actions'));

    $title = pht('Edit Actions (%s)', $repository->getName());

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-pencil');

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($repository)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions(
        pht(
          "Normally, Phabricator publishes notifications when it discovers ".
          "new commits. You can disable publishing for this repository by ".
          "turning off **Notify/Publish**. This will disable notifications, ".
          "feed, and Herald (including audits and build plans) for this ".
          "repository.\n\n".
          "When Phabricator discovers a new commit, it can automatically ".
          "close associated revisions and tasks. If you don't want ".
          "Phabricator to close objects when it discovers new commits in ".
          "this repository, you can disable **Autoclose**."))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('notify')
          ->setLabel(pht('Notify/Publish'))
          ->setValue((int)$v_notify)
          ->setOptions(
            array(
              1 => pht('Enable Notifications, Feed and Herald'),
              0 => pht('Disable Notifications, Feed and Herald'),
            )))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('autoclose')
          ->setLabel(pht('Autoclose'))
          ->setValue((int)$v_autoclose)
          ->setOptions(
            array(
              1 => pht('Enable Autoclose'),
              0 => pht('Disable Autoclose'),
            )))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Actions'))
          ->addCancelButton($edit_uri));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Actions'))
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
