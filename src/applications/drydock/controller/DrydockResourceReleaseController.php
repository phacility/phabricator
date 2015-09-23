<?php

final class DrydockResourceReleaseController extends DrydockResourceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $resource = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$resource) {
      return new Aphront404Response();
    }

    $resource_uri = '/resource/'.$resource->getID().'/';
    $resource_uri = $this->getApplicationURI($resource_uri);

    if (!$resource->canRelease()) {
      return $this->newDialog()
        ->setTitle(pht('Resource Not Releasable'))
        ->appendParagraph(
          pht(
            'Resources can not be released after they are destroyed.'))
        ->addCancelButton($resource_uri);
    }

    if ($request->isFormPost()) {
      $command = DrydockCommand::initializeNewCommand($viewer)
        ->setTargetPHID($resource->getPHID())
        ->setCommand(DrydockCommand::COMMAND_RELEASE)
        ->save();

      $resource->scheduleUpdate();

      return id(new AphrontRedirectResponse())->setURI($resource_uri);
    }


    return $this->newDialog()
      ->setTitle(pht('Really release resource?'))
      ->appendChild(
        pht(
          'Releasing a resource releases all leases and destroys the '.
          'resource. It can not be undone.'))
      ->addSubmitButton(pht('Release Resource'))
      ->addCancelButton($resource_uri);
  }

}
