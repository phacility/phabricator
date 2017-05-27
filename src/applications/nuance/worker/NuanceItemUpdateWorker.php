<?php

final class NuanceItemUpdateWorker
  extends NuanceWorker {

  protected function doWork() {
    $item_phid = $this->getTaskDataValue('itemPHID');

    $lock = $this->newLock($item_phid);

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

    $commands = id(new NuanceItemCommandQuery())
      ->setViewer($viewer)
      ->withItemPHIDs(array($item->getPHID()))
      ->withStatuses(
        array(
          NuanceItemCommand::STATUS_ISSUED,
        ))
      ->execute();
    $commands = msort($commands, 'getID');

    $this->executeCommandList($item, $commands);
  }

  public function executeCommands(NuanceItem $item, array $commands) {
    if (!$commands) {
      return true;
    }

    $item_phid = $item->getPHID();
    $viewer = $this->getViewer();

    $lock = $this->newLock($item_phid);
    try {
      $lock->lock(1);
    } catch (PhutilLockException $ex) {
      return false;
    }

    try {
      $item = $this->loadItem($item_phid);

      // Reload commands now that we have a lock, to make sure we don't
      // execute any commands twice by mistake.
      $commands = id(new NuanceItemCommandQuery())
        ->setViewer($viewer)
        ->withIDs(mpull($commands, 'getID'))
        ->execute();

      $this->executeCommandList($item, $commands);
    } catch (Exception $ex) {
      $lock->unlock();
      throw $ex;
    }

    $lock->unlock();

    return true;
  }

  private function executeCommandList(NuanceItem $item, array $commands) {
    $viewer = $this->getViewer();

    $executors = NuanceCommandImplementation::getAllCommands();
    foreach ($commands as $command) {
      if ($command->getItemPHID() !== $item->getPHID()) {
        throw new Exception(
          pht('Trying to apply a command to the wrong item!'));
      }

      if ($command->getStatus() !== NuanceItemCommand::STATUS_ISSUED) {
        // Never execute commands which have already been issued.
        continue;
      }

      $command
        ->setStatus(NuanceItemCommand::STATUS_EXECUTING)
        ->save();

      try {
        $command_key = $command->getCommand();

        $executor = idx($executors, $command_key);
        if (!$executor) {
          throw new Exception(
            pht(
              'Unable to execute command "%s": this command does not have '.
              'a recognized command implementation.',
              $command_key));
        }

        $executor = clone $executor;

        $executor
          ->setActor($viewer)
          ->applyCommand($item, $command);

        $command
          ->setStatus(NuanceItemCommand::STATUS_DONE)
          ->save();
      } catch (Exception $ex) {
        $command
          ->setStatus(NuanceItemCommand::STATUS_FAILED)
          ->save();

        throw $ex;
      }
    }
  }

  private function newLock($item_phid) {
    $hash = PhabricatorHash::digestForIndex($item_phid);
    $lock_key = "nuance.item.{$hash}";
    return PhabricatorGlobalLock::newLock($lock_key);
  }

}
