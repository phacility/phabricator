<?php

final class NuanceQueueViewController extends NuanceController {

  private $queueID;

  public function setQueueID($queue_id) {
    $this->queueID = $queue_id;
    return $this;
  }
  public function getQueueID() {
    return $this->queueID;
  }

  public function willProcessRequest(array $data) {
    $this->setQueueID($data['id']);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $queue_id = $this->getQueueID();
    $queue = id(new NuanceQueueQuery())
      ->setViewer($user)
      ->withIDs(array($queue_id))
      ->executeOne();

    if (!$queue) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();
    $title = pht('TODO');

    return $this->buildApplicationPage(
      $crumbs,
      array(
        'title' => $title,
      ));
  }
}
