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

    $executors = NuanceCommandImplementation::getAllCommands();
    $executor = idx($executors, $action);
    if (!$executor) {
      return new Aphront404Response();
    }

    $executor = id(clone $executor)
      ->setActor($viewer);

    if (!$executor->canApplyToItem($item)) {
      return $this->newDialog()
        ->setTitle(pht('Command Not Supported'))
        ->appendParagraph(
          pht(
            'This item does not support the specified command ("%s").',
            $action))
        ->addCancelButton($cancel_uri);
    }

    $command = NuanceItemCommand::initializeNewCommand()
      ->setItemPHID($item->getPHID())
      ->setAuthorPHID($viewer->getPHID())
      ->setCommand($action);

    if ($queue) {
      $command->setQueuePHID($queue->getPHID());
    }

    $command->save();

    // If this command can be applied immediately, try to apply it now.

    // In most cases, local commands (like closing an item) can be applied
    // immediately.

    // Commands that require making a call to a remote system (for example,
    // to reply to a tweet or close a remote object) are usually done in the
    // background so the user doesn't have to wait for the operation to
    // complete before they can continue work.

    $did_apply = false;
    $immediate = $executor->canApplyImmediately($item, $command);
    if ($immediate) {
      // TODO: Move this stuff to a new Engine, and have the controller and
      // worker both call into the Engine.
      $worker = new NuanceItemUpdateWorker(array());
      $did_apply = $worker->executeCommands($item, array($command));
    }

    // If this can't be applied immediately or we were unable to get a lock
    // fast enough, do the update in the background instead.
    if (!$did_apply) {
      $item->scheduleUpdate();
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
