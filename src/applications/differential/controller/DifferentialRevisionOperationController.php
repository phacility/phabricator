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

    if ($request->isFormPost()) {
      // NOTE: The operation is locked to the current active diff, so if the
      // revision is updated before the operation applies nothing sneaky
      // occurs.

      $diff = $revision->getActiveDiff();
      $repository = $revision->getRepository();

      $operation = DrydockRepositoryOperation::initializeNewOperation($op)
        ->setAuthorPHID($viewer->getPHID())
        ->setObjectPHID($revision->getPHID())
        ->setRepositoryPHID($repository->getPHID())
        ->setRepositoryTarget('branch:master')
        ->setProperty('differential.diffPHID', $diff->getPHID());

      $operation->save();
      $operation->scheduleUpdate();

      return id(new AphrontRedirectResponse())
        ->setURI($detail_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Land Revision'))
      ->appendParagraph(
        pht(
          'In theory, this will do approximately what `arc land` would do. '.
          'In practice, that is almost certainly not what it will actually '.
          'do.'))
      ->appendParagraph(
        pht(
          'THIS FEATURE IS EXPERIMENTAL AND DANGEROUS! USE IT AT YOUR '.
          'OWN RISK!'))
      ->addCancelButton($detail_uri)
      ->addSubmitButton(pht('Mutate Repository Unpredictably'));
  }

}
