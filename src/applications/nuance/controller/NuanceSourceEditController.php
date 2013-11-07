<?php

final class NuanceSourceEditController extends NuanceController {

  private $sourceID;

  public function setSourceID($source_id) {
    $this->sourceID = $source_id;
    return $this;
  }
  public function getSourceID() {
    return $this->sourceID;
  }

  public function willProcessRequest(array $data) {
    $this->setSourceID(idx($data, 'id'));
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $source_id = $this->getSourceID();
    $is_new = !$source_id;

    if ($is_new) {
      $source = new NuanceSource();

    } else {
      $source = id(new NuanceSourceQuery())
        ->setViewer($user)
        ->withIDs(array($source_id))
        ->executeOne();
    }

    if (!$source) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();
    $title = 'TODO';

    return $this->buildApplicationPage(
      $crumbs,
      array(
        'title' => $title,
        'device' => true));
  }
}
