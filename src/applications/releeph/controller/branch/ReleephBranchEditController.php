<?php

final class ReleephBranchEditController extends ReleephProjectController {

  public function processRequest() {
    $request = $this->getRequest();
    $releeph_branch = $this->getReleephBranch();
    $symbolic_name = $request->getStr(
      'symbolicName',
      $releeph_branch->getSymbolicName());

    $errors = array();

    if ($request->isFormPost()) {
      $existing_with_same_symbolic_name =
        id(new ReleephBranch())
          ->loadOneWhere(
              'id != %d AND releephProjectID = %d AND symbolicName = %s',
              $releeph_branch->getID(),
              $releeph_branch->getReleephProjectID(),
              $symbolic_name);

      $releeph_branch->openTransaction();
      $releeph_branch
        ->setSymbolicName($symbolic_name);

      if ($existing_with_same_symbolic_name) {
        $existing_with_same_symbolic_name
          ->setSymbolicName(null)
          ->save();
      }

      $releeph_branch->save();
      $releeph_branch->saveTransaction();

      return id(new AphrontRedirectResponse())
        ->setURI($releeph_branch->getURI());
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
        ->setLabel(pht('Branch Name'))
        ->setValue($releeph_branch->getName()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Cut Point'))
          ->setValue($handles[$cut_commit_phid]->renderLink()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Created By'))
          ->setValue($handles[$creator_phid]->renderLink()))
      ->appendChild(
        id(new AphrontFormTextControl)
          ->setLabel(pht('Symbolic Name'))
          ->setName('symbolicName')
          ->setValue($symbolic_name)
          ->setCaption(pht('Mutable alternate name, for easy reference, '.
              '(e.g. "LATEST")')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($releeph_branch->getURI())
          ->setValue(pht('Save')));

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_ERROR)
        ->setErrors($errors)
        ->setTitle(pht('Errors'));
    }

    $title = pht(
      'Edit Branch %s',
      $releeph_branch->getDisplayNameWithDetail());

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Edit')));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $error_view,
        $form,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }
}
