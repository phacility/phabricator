<?php

final class DrydockResourceCloseController extends DrydockResourceController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $resource = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$resource) {
      return new Aphront404Response();
    }

    $resource_uri = '/resource/'.$resource->getID().'/';
    $resource_uri = $this->getApplicationURI($resource_uri);

    if ($resource->getStatus() != DrydockResourceStatus::STATUS_OPEN) {
      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setTitle(pht('Resource Not Open'))
        ->appendChild(phutil_tag('p', array(), pht(
          'You can only close "open" resources.')))
        ->addCancelButton($resource_uri);

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    if ($request->isFormPost()) {
      $resource->closeResource();
      return id(new AphrontReloadResponse())->setURI($resource_uri);
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Really close resource?'))
      ->appendChild(
        pht(
          'Closing a resource releases all leases and destroys the '.
          'resource. It can not be undone. Continue?'))
      ->addSubmitButton(pht('Close Resource'))
      ->addCancelButton($resource_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
