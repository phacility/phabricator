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
        $blueprint = $candidate->getBlueprint();
      } catch (Exception $ex) {
        unset($pool[$key]);
        continue;
      }

      if ($blueprint->filterResource($candidate, $lease)) {
        $candidates[] = $candidate;
      }
    }

    $this->log(pht('%d Open Resource(s) Remain', count($candidates)));

    $resource = null;
    if ($candidates) {
      shuffle($candidates);
      foreach ($candidates as $candidate_resource) {
        $blueprint = $candidate_resource->getBlueprint();
        if ($blueprint->allocateLease($candidate_resource, $lease)) {
          $resource = $candidate_resource;
          break;
        }
      }
    }

    if (!$resource) {
      $blueprints = DrydockBlueprint::getAllBlueprintsForResource($type);

      $this->log(
        pht('Found %d Blueprints', count($blueprints)));

      foreach ($blueprints as $key => $candidate_blueprint) {
        if (!$candidate_blueprint->isEnabled()) {
          unset($blueprints[$key]);
          continue;
        }
      }

      $this->log(
        pht('%d Blueprints Enabled', count($blueprints)));

      foreach ($blueprints as $key => $candidate_blueprint) {
        if (!$candidate_blueprint->canAllocateMoreResources($pool)) {
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

      if (!$blueprint->allocateLease($resource, $lease)) {
        // TODO: This "should" happen only if we lost a race with another lease,
        // which happened to acquire this resource immediately after we
        // allocated it. In this case, the right behavior is to retry
        // immediately. However, other things like a blueprint allocating a
        // resource it can't actually allocate the lease on might be happening
        // too, in which case we'd just allocate infinite resources. Probably
        // what we should do is test for an active or allocated lease and retry
        // if we find one (although it might have already been released by now)
        // and fail really hard ("your configuration is a huge broken mess")
        // otherwise. But just throw for now since this stuff is all edge-casey.
        // Alternatively we could bring resources up in a "BESPOKE" status
        // and then switch them to "OPEN" only after the allocating lease gets
        // its grubby mitts on the resource. This might make more sense but
        // is a bit messy.
        throw new Exception("Lost an allocation race?");
      }
    }

    $blueprint = $resource->getBlueprint();
    $blueprint->acquireLease($resource, $lease);
  }

}


