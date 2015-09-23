<?php

final class DrydockLeaseReleaseController extends DrydockLeaseController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $lease = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$lease) {
      return new Aphront404Response();
    }

    $lease_uri = '/lease/'.$lease->getID().'/';
    $lease_uri = $this->getApplicationURI($lease_uri);

    if (!$lease->canRelease()) {
      return $this->newDialog()
        ->setTitle(pht('Lease Not Releasable'))
        ->appendParagraph(
          pht(
            'Leases can not be released after they are destroyed.'))
        ->addCancelButton($lease_uri);
    }

    if ($request->isFormPost()) {
      $command = DrydockCommand::initializeNewCommand($viewer)
        ->setTargetPHID($lease->getPHID())
        ->setCommand(DrydockCommand::COMMAND_RELEASE)
        ->save();

      $lease->scheduleUpdate();

      return id(new AphrontRedirectResponse())->setURI($lease_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Release Lease?'))
      ->appendParagraph(
        pht(
          'Forcefully releasing a lease may interfere with the operation '.
          'of the lease holder and trigger destruction of the underlying '.
          'resource. It can not be undone.'))
      ->addSubmitButton(pht('Release Lease'))
      ->addCancelButton($lease_uri);
  }

}
