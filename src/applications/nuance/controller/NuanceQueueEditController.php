<?php

final class NuanceQueueEditController extends NuanceController {

  private $queueID;

  public function setQueueID($queue_id) {
    $this->queueID = $queue_id;
    return $this;
  }
  public function getQueueID() {
    return $this->queueID;
  }

  public function willProcessRequest(array $data) {
    $this->setQueueID(idx($data, 'id'));
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $queue_id = $this->getQueueID();
    $is_new = !$queue_id;

    if ($is_new) {
      $queue = new NuanceQueue();

    } else {
      $queue = id(new NuanceQueueQuery())
        ->setViewer($user)
        ->withIDs(array($queue_id))
        ->executeOne();
    }

    if (!$queue) {
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
