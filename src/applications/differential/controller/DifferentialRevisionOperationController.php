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

    $ref_types = array(
      PhabricatorRepositoryRefCursor::TYPE_BRANCH,
    );

    $e_ref = true;

    $errors = array();
    if ($request->isFormPost()) {

      $ref_phid = head($request->getArr('refPHIDs'));
      if (!strlen($ref_phid)) {
        $e_ref = pht('Required');
        $errors[] = pht(
          'You must select a branch to land this revision onto.');
      } else {
        $ref = id(new PhabricatorRepositoryRefCursorQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($ref_phid))
          ->withRepositoryPHIDs(array($repository->getPHID()))
          ->withRefTypes($ref_types)
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
          'refTypes' => $ref_types,
        ));

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions(
        pht(
          'In theory, this will do approximately what `arc land` would do. '.
          'In practice, you will have a riveting adventure instead.'))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Onto Branch'))
          ->setName('refPHIDs')
          ->setLimit(1)
          ->setError($e_ref)
          ->setDatasource($ref_datasource))
      ->appendRemarkupInstructions(
        pht(
          '(WARNING) THIS FEATURE IS EXPERIMENTAL AND DANGEROUS! USE IT AT '.
          'YOUR OWN RISK!'));

    return $this->newDialog()
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle(pht('Land Revision'))
      ->setErrors($errors)
      ->appendForm($form)
      ->addCancelButton($detail_uri)
      ->addSubmitButton(pht('Mutate Repository Unpredictably'));
  }

}
