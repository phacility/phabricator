<?php

final class DifferentialRevisionOperationController
  extends DifferentialController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $revision = id(new DifferentialRevisionQuery())
      ->withIDs(array($id))
      ->setViewer($viewer)
      ->needActiveDiffs(true)
      ->executeOne();
    if (!$revision) {
      return new Aphront404Response();
    }

    $detail_uri = "/D{$id}";

    $op = new DrydockLandRepositoryOperation();
    $barrier = $op->getBarrierToLanding($viewer, $revision);
    if ($barrier) {
      return $this->newDialog()
        ->setTitle($barrier['title'])
        ->appendParagraph($barrier['body'])
        ->addCancelButton($detail_uri);
    }

    $diff = $revision->getActiveDiff();
    $repository = $revision->getRepository();

    $default_ref = $this->loadDefaultRef($repository, $diff);

    if ($default_ref) {
      $v_ref = array($default_ref->getPHID());
    } else {
      $v_ref = array();
    }

    $e_ref = true;

    $errors = array();
    if ($request->isFormPost()) {

      $v_ref = $request->getArr('refPHIDs');
      $ref_phid = head($v_ref);
      if (!strlen($ref_phid)) {
        $e_ref = pht('Required');
        $errors[] = pht(
          'You must select a branch to land this revision onto.');
      } else {
        $ref = $this->newRefQuery($repository)
          ->withPHIDs(array($ref_phid))
          ->executeOne();
        if (!$ref) {
          $e_ref = pht('Invalid');
          $errors[] = pht(
            'You must select a branch from this repository to land this '.
            'revision onto.');
        }
      }

      if (!$errors) {
        // NOTE: The operation is locked to the current active diff, so if the
        // revision is updated before the operation applies nothing sneaky
        // occurs.

        $target = 'branch:'.$ref->getRefName();

        $operation = DrydockRepositoryOperation::initializeNewOperation($op)
          ->setAuthorPHID($viewer->getPHID())
          ->setObjectPHID($revision->getPHID())
          ->setRepositoryPHID($repository->getPHID())
          ->setRepositoryTarget($target)
          ->setProperty('differential.diffPHID', $diff->getPHID());

        $operation->save();
        $operation->scheduleUpdate();

        return id(new AphrontRedirectResponse())
          ->setURI($detail_uri);
      }
    }

    $ref_datasource = id(new DiffusionRefDatasource())
      ->setParameters(
        array(
          'repositoryPHIDs' => array($repository->getPHID()),
          'refTypes' => $this->getTargetableRefTypes(),
        ));

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions(
        pht(
          '(NOTE) This feature is new and experimental.'))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Onto Branch'))
          ->setName('refPHIDs')
          ->setLimit(1)
          ->setError($e_ref)
          ->setValue($v_ref)
          ->setDatasource($ref_datasource));

    return $this->newDialog()
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle(pht('Land Revision'))
      ->setErrors($errors)
      ->appendForm($form)
      ->addCancelButton($detail_uri)
      ->addSubmitButton(pht('Land Revision'));
  }

  private function newRefQuery(PhabricatorRepository $repository) {
    $viewer = $this->getViewer();

    return id(new PhabricatorRepositoryRefCursorQuery())
      ->setViewer($viewer)
      ->withRepositoryPHIDs(array($repository->getPHID()))
      ->withRefTypes($this->getTargetableRefTypes());
  }

  private function getTargetableRefTypes() {
    return array(
      PhabricatorRepositoryRefCursor::TYPE_BRANCH,
    );
  }

  private function loadDefaultRef(
    PhabricatorRepository $repository,
    DifferentialDiff $diff) {
    $default_name = $this->getDefaultRefName($repository, $diff);

    if (!strlen($default_name)) {
      return null;
    }

    return $this->newRefQuery($repository)
      ->withRefNames(array($default_name))
      ->executeOne();
  }

  private function getDefaultRefName(
    PhabricatorRepository $repository,
    DifferentialDiff $diff) {

    $onto = $diff->loadTargetBranch();
    if ($onto !== null) {
      return $onto;
    }

    return $repository->getDefaultBranch();
  }

}
