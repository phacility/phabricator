<?php

final class NuanceRequestorViewController extends NuanceController {

  private $requestorID;

  public function setRequestorID($requestor_id) {
    $this->requestorID = $requestor_id;
    return $this;
  }
  public function getRequestorID() {
    return $this->requestorID;
  }

  public function willProcessRequest(array $data) {
    $this->setRequestorID($data['id']);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $requestor_id = $this->getRequestorID();
    $requestor = id(new NuanceRequestorQuery())
      ->setViewer($user)
      ->withIDs(array($requestor_id))
      ->executeOne();

    if (!$requestor) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();
    $title = 'TODO';

    return $this->buildApplicationPage(
      $crumbs,
      array(
        'title' => $title,
      ));
  }
}
