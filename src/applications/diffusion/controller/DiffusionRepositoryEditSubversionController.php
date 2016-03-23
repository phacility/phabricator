<?php

final class DiffusionRepositoryEditSubversionController
  extends DiffusionRepositoryEditController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContextForEdit();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        throw new Exception(
          pht('Git and Mercurial do not support editing SVN properties!'));
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        break;
      default:
        throw new Exception(
          pht('Repository has unknown version control system!'));
    }

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

    $v_subpath = $repository->getHumanReadableDetail('svn-subpath');
    $v_uuid = $repository->getUUID();

    if ($request->isFormPost()) {
      $v_subpath = $request->getStr('subpath');
      $v_uuid = $request->getStr('uuid');

      $xactions = array();
      $template = id(new PhabricatorRepositoryTransaction());

      $type_subpath = PhabricatorRepositoryTransaction::TYPE_SVN_SUBPATH;
      $type_uuid = PhabricatorRepositoryTransaction::TYPE_UUID;

      $xactions[] = id(clone $template)
        ->setTransactionType($type_subpath)
        ->setNewValue($v_subpath);

      $xactions[] = id(clone $template)
        ->setTransactionType($type_uuid)
        ->setNewValue($v_uuid);

      id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->applyTransactions($repository, $xactions);

      return id(new AphrontRedirectResponse())->setURI($edit_uri);
    }

    $content = array();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Subversion Info'));

    $title = pht('Edit Subversion Info (%s)', $repository->getName());
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
          "You can set the **Repository UUID**, which will help Phabriactor ".
          "provide better context in some cases. You can find the UUID of a ".
          "repository by running `%s`.\n\n".
          "If you want to import only part of a repository, like `trunk/`, ".
          "you can set a path in **Import Only**. Phabricator will ignore ".
          "commits which do not affect this path.",
          'svn info'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('uuid')
          ->setLabel(pht('Repository UUID'))
          ->setValue($v_uuid))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('subpath')
          ->setLabel(pht('Import Only'))
          ->setValue($v_subpath))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Subversion Info'))
          ->addCancelButton($edit_uri));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Subversion'))
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
