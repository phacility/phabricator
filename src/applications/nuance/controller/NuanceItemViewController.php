<?php

final class NuanceItemViewController extends NuanceController {

  private $itemID;

  public function setItemID($item_id) {
    $this->itemID = $item_id;
    return $this;
  }
  public function getItemID() {
    return $this->itemID;
  }

  public function willProcessRequest(array $data) {
    $this->setItemID($data['id']);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $item_id = $this->getItemID();
    $item = id(new NuanceItemQuery())
      ->setViewer($user)
      ->withIDs(array($item_id))
      ->executeOne();

    if (!$item) {
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
