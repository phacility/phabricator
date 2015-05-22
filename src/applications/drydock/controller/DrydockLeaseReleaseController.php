<?php

final class DrydockLeaseReleaseController extends DrydockLeaseController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $lease = id(new DrydockLeaseQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$lease) {
      return new Aphront404Response();
    }

    $lease_uri = '/lease/'.$lease->getID().'/';
    $lease_uri = $this->getApplicationURI($lease_uri);

    if ($lease->getStatus() != DrydockLeaseStatus::STATUS_ACTIVE) {
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Lease Not Active'))
        ->appendChild(
          phutil_tag(
            'p',
            array(),
            pht('You can only release "active" leases.')))
        ->addCancelButton($lease_uri);

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    if (!$request->isDialogFormPost()) {
      $dialog = id(new AphrontDialogView())
        ->setUser($user)
        ->setTitle(pht('Really release lease?'))
        ->appendChild(
          phutil_tag(
            'p',
            array(),
            pht(
              'Releasing a lease may cause trouble for the lease holder and '.
              'trigger cleanup of the underlying resource. It can not be '.
              'undone. Continue?')))
        ->addSubmitButton(pht('Release Lease'))
        ->addCancelButton($lease_uri);

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $resource = $lease->getResource();
    $blueprint = $resource->getBlueprint();
    $blueprint->releaseLease($resource, $lease);

    return id(new AphrontReloadResponse())->setURI($lease_uri);
  }

}
