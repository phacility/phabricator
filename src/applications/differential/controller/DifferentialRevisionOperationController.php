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

    $repository = $revision->getRepository();
    if (!$repository) {
      return $this->rejectOperation(
        $revision,
        pht('No Repository'),
        pht(
          'This revision is not associated with a known repository. Only '.
          'revisions associated with a tracked repository can be landed '.
          'automatically.'));
    }

    if (!$repository->canPerformAutomation()) {
      return $this->rejectOperation(
        $revision,
        pht('No Repository Automation'),
        pht(
          'The repository this revision is associated with ("%s") is not '.
          'configured to support automation. Configure automation for the '.
          'repository to enable revisions to be landed automatically.',
          $repository->getMonogram()));
    }

    // TODO: At some point we should allow installs to give "land reviewed
    // code" permission to more users than "push any commit", because it is
    // a much less powerful operation. For now, just require push so this
    // doesn't do anything users can't do on their own.
    $can_push = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      DiffusionPushCapability::CAPABILITY);
    if (!$can_push) {
      return $this->rejectOperation(
        $revision,
        pht('Unable to Push'),
        pht(
          'You do not have permission to push to the repository this '.
          'revision is associated with ("%s"), so you can not land it.',
          $repository->getMonogram()));
    }

    if ($request->isFormPost()) {
      // NOTE: The operation is locked to the current active diff, so if the
      // revision is updated before the operation applies nothing sneaky
      // occurs.

      $diff = $revision->getActiveDiff();

      $op = new DrydockLandRepositoryOperation();

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

  private function rejectOperation(
    DifferentialRevision $revision,
    $title,
    $body) {

    $id = $revision->getID();
    $detail_uri = "/D{$id}";

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addCancelButton($detail_uri);
  }

}
