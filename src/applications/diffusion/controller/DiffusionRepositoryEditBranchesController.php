<?php

final class DiffusionRepositoryEditBranchesController
  extends DiffusionRepositoryEditController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContextForEdit();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

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
    $v_track = $repository->getDetail(
      'branch-filter',
      array());
    $v_track = array_keys($v_track);
    $v_autoclose = $repository->getDetail(
      'close-commits-filter',
      array());
    $v_autoclose = array_keys($v_autoclose);

    $e_track = null;
    $e_autoclose = null;

    $validation_exception = null;
    if ($request->isFormPost()) {
      $v_default = $request->getStr('default');

      $v_track = $this->processBranches($request->getStr('track'));
      if (!$is_hg) {
        $v_autoclose = $this->processBranches($request->getStr('autoclose'));
      }

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

      $editor = id(new PhabricatorRepositoryEditor())
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request)
        ->setActor($viewer);

      try {
        $editor->applyTransactions($repository, $xactions);
        return id(new AphrontRedirectResponse())->setURI($edit_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        $e_track = $validation_exception->getShortMessage($type_track);
        $e_autoclose = $validation_exception->getShortMessage($type_autoclose);
      }
    }

    $content = array();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Edit Branches'));

    $title = pht('Edit Branches (%s)', $repository->getName());
    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-pencil');

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($repository)
      ->execute();

    $rows = array();
    $rows[] = array(
      array(
        'master',
      ),
      pht('Select only master.'),
    );
    $rows[] = array(
      array(
        'master',
        'develop',
        'release',
      ),
      pht('Select %s, %s, and %s.', 'master', 'develop', 'release'),
    );
    $rows[] = array(
      array(
        'master',
        'regexp(/^release-/)',
      ),
      pht('Select master, and all branches which start with "%s".', 'release-'),
    );
    $rows[] = array(
      array(
        'regexp(/^(?!temp-)/)',
      ),
      pht('Select all branches which do not start with "%s".', 'temp-'),
    );

    foreach ($rows as $k => $row) {
      $rows[$k][0] = phutil_tag(
        'pre',
        array(),
        implode("\n", $row[0]));
    }

    $example_table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Example'),
          pht('Effect'),
        ))
      ->setColumnClasses(
        array(
          '',
          'wide',
        ));

    $v_track = implode("\n", $v_track);
    $v_autoclose = implode("\n", $v_autoclose);

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions(
        pht('You can choose a **Default Branch** for viewing this repository.'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('default')
          ->setLabel(pht('Default Branch'))
          ->setValue($v_default))
      ->appendRemarkupInstructions(
        pht(
          'If you want to import only some branches into Diffusion, you can '.
          'list them in **Track Only**. Other branches will be ignored. If '.
          'you do not specify any branches, all branches are tracked.'));

    if (!$is_hg) {
      $form->appendRemarkupInstructions(
        pht(
          'If you have **Autoclose** enabled for this repository, Phabricator '.
          'can close tasks and revisions when corresponding commits are '.
          'pushed to the repository. If you want to autoclose objects only '.
          'when commits appear on specific branches, you can list those '.
          'branches in **Autoclose Only**. By default, all tracked branches '.
          'will autoclose objects.'));
    }

    $form
      ->appendRemarkupInstructions(
        pht(
          'When specifying branches, you should enter one branch name per '.
          'line. You can use regular expressions to match branches by '.
          'wrapping an expression in `%s`. For example:',
          'regexp(...)'))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue($example_table))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setName('track')
          ->setLabel(pht('Track Only'))
          ->setError($e_track)
          ->setValue($v_track));

    if (!$is_hg) {
      $form->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setName('autoclose')
          ->setLabel(pht('Autoclose Only'))
          ->setError($e_autoclose)
          ->setValue($v_autoclose));
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save Branches'))
        ->addCancelButton($edit_uri));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Branches'))
      ->setValidationException($validation_exception)
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

  private function processBranches($string) {
    $lines = phutil_split_lines($string, $retain_endings = false);
    foreach ($lines as $key => $line) {
      $lines[$key] = trim($line);
      if (!strlen($lines[$key])) {
        unset($lines[$key]);
      }
    }

    return array_values($lines);
  }

}
