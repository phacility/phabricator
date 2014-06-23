<?php

final class DiffusionRepositoryEditBranchesController
  extends DiffusionRepositoryEditController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $drequest = $this->diffusionRequest;
    $repository = $drequest->getRepository();

    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($repository->getID()))
      ->executeOne();
    if (!$repository) {
      return new Aphront404Response();
    }

    $is_git = false;
    $is_hg = false;

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $is_git = true;
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $is_hg = true;
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        throw new Exception(
          pht('Subversion does not support branches!'));
      default:
        throw new Exception(
          pht('Repository has unknown version control system!'));
    }

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

    $v_default = $repository->getHumanReadableDetail('default-branch');
    $v_track = $repository->getHumanReadableDetail(
      'branch-filter',
      array());
    $v_autoclose = $repository->getHumanReadableDetail(
      'close-commits-filter',
      array());

    if ($request->isFormPost()) {
      $v_default = $request->getStr('default');
      $v_track = $request->getStrList('track');
      $v_autoclose = $request->getStrList('autoclose');

      $xactions = array();
      $template = id(new PhabricatorRepositoryTransaction());

      $type_default = PhabricatorRepositoryTransaction::TYPE_DEFAULT_BRANCH;
      $type_track = PhabricatorRepositoryTransaction::TYPE_TRACK_ONLY;
      $type_autoclose = PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE_ONLY;

      $xactions[] = id(clone $template)
        ->setTransactionType($type_default)
        ->setNewValue($v_default);

      $xactions[] = id(clone $template)
        ->setTransactionType($type_track)
        ->setNewValue($v_track);

      if (!$is_hg) {
        $xactions[] = id(clone $template)
          ->setTransactionType($type_autoclose)
          ->setNewValue($v_autoclose);
      }

      id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->applyTransactions($repository, $xactions);

      return id(new AphrontRedirectResponse())->setURI($edit_uri);
    }

    $content = array();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Branches'));

    $title = pht('Edit Branches (%s)', $repository->getName());

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($repository)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions(
        pht(
          'You can choose a **Default Branch** for viewing this repository.'.
          "\n\n".
          'If you want to import only some branches into Diffusion, you can '.
          'list them in **Track Only**. Other branches will be ignored. If '.
          'you do not specify any branches, all branches are tracked.'.
          "\n\n".
          'If you have **Autoclose** enabled, Phabricator can close tasks and '.
          'revisions when corresponding commits are pushed to the repository. '.
          'If you want to autoclose objects only when commits appear on '.
          'specific branches, you can list those branches in **Autoclose '.
          'Only**. By default, all branches autoclose objects.'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('default')
          ->setLabel(pht('Default Branch'))
          ->setValue($v_default)
          ->setCaption(
            pht('Example: %s', phutil_tag('tt', array(), 'develop'))))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('track')
          ->setLabel(pht('Track Only'))
          ->setValue($v_track)
          ->setCaption(
            pht('Example: %s', phutil_tag('tt', array(), 'master, develop'))));

    if (!$is_hg) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setName('autoclose')
          ->setLabel(pht('Autoclose Only'))
          ->setValue($v_autoclose)
          ->setCaption(
            pht('Example: %s', phutil_tag('tt', array(), 'master, release'))));
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save Branches'))
        ->addCancelButton($edit_uri));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => $title,
      ));
  }

}
