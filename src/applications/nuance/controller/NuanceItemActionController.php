<?php

final class NuanceItemActionController extends NuanceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    if (!$request->validateCSRF()) {
      return new Aphront400Response();
    }

    // NOTE: This controller can be reached from an individual item (usually
    // by a user) or while working through a queue (usually by staff). When
    // a command originates from a queue, the URI will have a queue ID.

    $item = id(new NuanceItemQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$item) {
      return new Aphront404Response();
    }

    $cancel_uri = $item->getURI();

    $queue_id = $request->getURIData('queueID');
    $queue = null;
    if ($queue_id) {
      $queue = id(new NuanceQueueQuery())
        ->setViewer($viewer)
        ->withIDs(array($queue_id))
        ->executeOne();
      if (!$queue) {
        return new Aphront404Response();
      }

      $item_queue = $item->getQueue();
      if (!$item_queue || ($item_queue->getPHID() != $queue->getPHID())) {
        return $this->newDialog()
          ->setTitle(pht('Wrong Queue'))
          ->appendParagraph(
            pht(
              'You are trying to act on this item from the wrong queue: it '.
              'is currently in a different queue.'))
          ->addCancelButton($cancel_uri);
      }
    }

    $action = $request->getURIData('action');

    $impl = $item->getImplementation();
    $impl->setViewer($viewer);
    $impl->setController($this);

    $command = NuanceItemCommand::initializeNewCommand()
      ->setItemPHID($item->getPHID())
      ->setAuthorPHID($viewer->getPHID())
      ->setCommand($action);

    if ($queue) {
      $command->setQueuePHID($queue->getPHID());
    }

    $command->save();

    // TODO: Here, we should check if the command should be tried immediately,
    // and just defer it to the daemons if not. If we're going to try to apply
    // the command directly, we should first acquire the worker lock. If we
    // can not, we should defer the command even if it's an immediate command.
    // For the moment, skip all this stuff by deferring unconditionally.

    $should_defer = true;
    if ($should_defer) {
      $item->scheduleUpdate();
    } else {
      // ...
    }

    if ($queue) {
      $done_uri = $queue->getWorkURI();
    } else {
      $done_uri = $item->getURI();
    }

    return id(new AphrontRedirectResponse())
      ->setURI($done_uri);
  }

}
