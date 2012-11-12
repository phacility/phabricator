<?php

final class DrydockAllocatorWorker extends PhabricatorWorker {

  private $lease;

  public function getMaximumRetryCount() {
    // TODO: Allow Drydock allocations to retry. For now, every failure is
    // permanent and most of them are because I am bad at programming, so fail
    // fast rather than ending up in limbo.
    return 0;
  }

  private function loadLease() {
    if (empty($this->lease)) {
      $lease = id(new DrydockLease())->load($this->getTaskData());
      if (!$lease) {
        throw new PhabricatorWorkerPermanentFailureException(
          "No such lease!");
      }
      $this->lease = $lease;
    }
    return $this->lease;
  }

  private function log($message) {
    DrydockBlueprint::writeLog(
      null,
      $this->loadLease(),
      $message);
  }

  protected function doWork() {
    $lease = $this->loadLease();
    $this->log('Allocating Lease');

    try {
      $this->allocateLease($lease);
    } catch (Exception $ex) {

      // TODO: We should really do this when archiving the task, if we've
      // suffered a permanent failure. But we don't have hooks for that yet
      // and always fail after the first retry right now, so this is
      // functionally equivalent.
      $lease->reload();
      if ($lease->getStatus() == DrydockLeaseStatus::STATUS_PENDING) {
        $lease->setStatus(DrydockLeaseStatus::STATUS_BROKEN);
        $lease->save();
      }

      throw $ex;
    }
  }

  private function allocateLease(DrydockLease $lease) {
    $type = $lease->getResourceType();

    $pool = id(new DrydockResource())->loadAllWhere(
      'type = %s AND status = %s',
      $lease->getResourceType(),
      DrydockResourceStatus::STATUS_OPEN);

    $this->log(
      pht('Found %d Open Resource(s)', count($pool)));

    $candidates = array();
    foreach ($pool as $key => $candidate) {
      try {
        $candidate->getBlueprint();
      } catch (Exception $ex) {
        unset($pool[$key]);
      }

      // TODO: Filter candidates according to ability to satisfy the lease.

      $candidates[] = $candidate;
    }

    $this->log(
      pht('%d Open Resource(s) Remain', count($candidates)));

    if ($candidates) {
      shuffle($candidates);
      $resource = head($candidates);
    } else {
      $blueprints = DrydockBlueprint::getAllBlueprintsForResource($type);

      $this->log(
        pht('Found %d Blueprints', count($blueprints)));

      foreach ($blueprints as $key => $blueprint) {
        if (!$blueprint->isEnabled()) {
          unset($blueprints[$key]);
          continue;
        }
      }

      $this->log(
        pht('%d Blueprints Enabled', count($blueprints)));

      foreach ($blueprints as $key => $blueprint) {
        if (!$blueprint->canAllocateMoreResources($pool)) {
          unset($blueprints[$key]);
          continue;
        }
      }

      $this->log(
        pht('%d Blueprints Can Allocate', count($blueprints)));

      if (!$blueprints) {
        $lease->setStatus(DrydockLeaseStatus::STATUS_BROKEN);
        $lease->save();

        $this->log(
          "There are no resources of type '{$type}' available, and no ".
          "blueprints which can allocate new ones.");

        return;
      }

      // TODO: Rank intelligently.
      shuffle($blueprints);

      $blueprint = head($blueprints);
      $resource = $blueprint->allocateResource($lease);
    }

    $blueprint = $resource->getBlueprint();
    $blueprint->acquireLease($resource, $lease);
  }

}


