<?php

final class NuanceItemUpdateWorker
  extends NuanceWorker {

  protected function doWork() {
    $item_phid = $this->getTaskDataValue('itemPHID');

    $hash = PhabricatorHash::digestForIndex($item_phid);
    $lock_key = "nuance.item.{$hash}";
    $lock = PhabricatorGlobalLock::newLock($lock_key);

    $lock->lock(1);
    try {
      $item = $this->loadItem($item_phid);
      $this->updateItem($item);
      $this->routeItem($item);
      $this->applyCommands($item);
    } catch (Exception $ex) {
      $lock->unlock();
      throw $ex;
    }

    $lock->unlock();
  }

  private function updateItem(NuanceItem $item) {
    $impl = $item->getImplementation();
    if (!$impl->canUpdateItems()) {
      return null;
    }

    $viewer = $this->getViewer();

    $impl->setViewer($viewer);
    $impl->updateItem($item);
  }

  private function routeItem(NuanceItem $item) {
    $status = $item->getStatus();
    if ($status != NuanceItem::STATUS_ROUTING) {
      return;
    }

    $source = $item->getSource();

    // For now, always route items into the source's default queue.

    $item
      ->setQueuePHID($source->getDefaultQueuePHID())
      ->setStatus(NuanceItem::STATUS_OPEN)
      ->save();
  }

  private function applyCommands(NuanceItem $item) {
    $viewer = $this->getViewer();

    $impl = $item->getImplementation();
    $impl->setViewer($viewer);

    $commands = id(new NuanceItemCommandQuery())
      ->setViewer($viewer)
      ->withItemPHIDs(array($item->getPHID()))
      ->execute();
    $commands = msort($commands, 'getID');

    foreach ($commands as $command) {
      $impl->applyCommand($item, $command);
      $command->delete();
    }
  }

}
