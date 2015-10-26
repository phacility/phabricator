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

    $op = new DrydockLandRepositoryOperation();

    // Check for other operations. Eventually this should probably be more
    // general (e.g., it's OK to land to multiple different branches
    // simultaneously) but just put this in as a sanity check for now.
    $other_operations = id(new DrydockRepositoryOperationQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($revision->getPHID()))
      ->withOperationTypes(
        array(
          $op->getOperationConstant(),
        ))
      ->withOperationStates(
        array(
          DrydockRepositoryOperation::STATE_WAIT,
          DrydockRepositoryOperation::STATE_WORK,
          DrydockRepositoryOperation::STATE_DONE,
        ))
      ->execute();

    if ($other_operations) {
      $any_done = false;
      foreach ($other_operations as $operation) {
        if ($operation->isDone()) {
          $any_done = true;
          break;
        }
      }

      if ($any_done) {
        return $this->rejectOperation(
          $revision,
          pht('Already Complete'),
          pht('This revision has already landed.'));
      } else {
        return $this->rejectOperation(
          $revision,
          pht('Already In Flight'),
          pht('This revision is already landing.'));
      }
    }

    if ($request->isFormPost()) {
      // NOTE: The operation is locked to the current active diff, so if the
      // revision is updated before the operation applies nothing sneaky
      // occurs.

      $diff = $revision->getActiveDiff();

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
