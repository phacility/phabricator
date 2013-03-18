<?php

final class ReleephBranchEditController extends ReleephController {

  public function processRequest() {
    $request = $this->getRequest();
    $releeph_branch = $this->getReleephBranch();
    $branch_name = $request->getStr(
      'branchName',
      $releeph_branch->getName());
    $symbolic_name = $request->getStr(
      'symbolicName',
      $releeph_branch->getSymbolicName());

    $e_existing_with_same_branch_name = false;
    $errors = array();

    if ($request->isFormPost()) {
      $existing_with_same_branch_name =
        id(new ReleephBranch())
          ->loadOneWhere(
              'id != %d AND releephProjectID = %d AND name = %s',
              $releeph_branch->getID(),
              $releeph_branch->getReleephProjectID(),
              $branch_name);

      if ($existing_with_same_branch_name) {
        $errors[] = sprintf(
          "The branch name %s is currently taken. Please use another name. ",
          $branch_name);
        $e_existing_with_same_branch_name = 'Error';
      }

      if (!$errors) {
        $existing_with_same_symbolic_name =
          id(new ReleephBranch())
            ->loadOneWhere(
                'id != %d AND releephProjectID = %d AND symbolicName = %s',
                $releeph_branch->getID(),
                $releeph_branch->getReleephProjectID(),
                $symbolic_name);

        $releeph_branch->openTransaction();
        $releeph_branch
          ->setName($branch_name)
          ->setBasename(last(explode('/', $branch_name)))
          ->setSymbolicName($symbolic_name);

        if ($existing_with_same_symbolic_name) {
          $existing_with_same_symbolic_name
            ->setSymbolicName(null)
            ->save();
        }

        $releeph_branch->save();
        $releeph_branch->saveTransaction();

        return id(new AphrontRedirectResponse())
          ->setURI('/releeph/project/'.$releeph_branch->getReleephProjectID());
      }
    }

    $phids = array();

    $phids[] = $creator_phid = $releeph_branch->getCreatedByUserPHID();
    $phids[] = $cut_commit_phid = $releeph_branch->getCutPointCommitPHID();

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($request->getUser())
      ->loadHandles();

    $form = id(new AphrontFormView())
      ->setUser($request->getUser())
      ->appendChild(
        id(new AphrontFormStaticControl())
        ->setLabel('Branch name')
        ->setValue($branch_name))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Cut point')
          ->setValue($handles[$cut_commit_phid]->renderLink()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Created by')
          ->setValue($handles[$creator_phid]->renderLink()))
      ->appendChild(
        id(new AphrontFormTextControl)
          ->setLabel('Symbolic Name')
          ->setName('symbolicName')
          ->setValue($symbolic_name)
          ->setCaption('Mutable alternate name, for easy reference, '.
              '(e.g. "LATEST")'))
      ->appendChild(hsprintf(
        '<br>' .
        'In dire situations where the branch name is wrong, ' .
        'you can edit it in the database by changing the field below. ' .
        'If you do this, it is very important that you change your ' .
        'branch\'s name in the VCS to reflect the new name in Releeph, ' .
        'otherwise a catastrophe of previously unheard-of magnitude ' .
        'will befall your project.'))
      ->appendChild(
        id(new AphrontFormTextControl)
          ->setLabel('New branch name')
          ->setName('branchName')
          ->setValue($branch_name)
          ->setError($e_existing_with_same_branch_name))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($releeph_branch->getURI())
          ->setValue('Save'));

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_ERROR)
        ->setErrors($errors)
        ->setTitle('Errors');
    }

    $title = hsprintf(
      'Edit branch %s',
      $releeph_branch->getDisplayNameWithDetail());

    $panel = id(new AphrontPanelView())
      ->setHeader($title)
      ->appendChild($form)
      ->setWidth(AphrontPanelView::WIDTH_FORM);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array('title' => $title));
  }
}
