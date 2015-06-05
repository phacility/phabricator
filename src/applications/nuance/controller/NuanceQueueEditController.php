<?php

final class NuanceQueueEditController extends NuanceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $queue_id = $request->getURIData('id');
    $is_new = !$queue_id;
    if ($is_new) {
      $queue = NuanceQueue::initializeNewQueue();
    } else {
      $queue = id(new NuanceQueueQuery())
        ->setViewer($viewer)
        ->withIDs(array($queue_id))
        ->executeOne();
      if (!$queue) {
        return new Aphront404Response();
      }
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Queues'),
      $this->getApplicationURI('queue/'));

    if ($is_new) {
      $title = pht('Create Queue');
      $crumbs->addTextCrumb(pht('Create'));
    } else {
      $title = pht('Edit %s', $queue->getName());
      $crumbs->addTextCrumb($queue->getName(), $queue->getURI());
      $crumbs->addTextCrumb(pht('Edit'));
    }

    return $this->buildApplicationPage(
      $crumbs,
      array(
        'title' => $title,
      ));
  }

}
