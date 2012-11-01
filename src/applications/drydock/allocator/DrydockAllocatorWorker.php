<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class DrydockAllocatorWorker extends PhabricatorWorker {

  protected function doWork() {
    $data = $this->getTaskData();

    $lease = id(new DrydockLease())->loadOneWhere(
      'id = %d',
      $data['lease']);
    if (!$lease) {
      return;
    }

    $type = $data['type'];

    $candidates = id(new DrydockResource())->loadAllWhere(
      'type = %s AND status = %s',
      $type,
      DrydockResourceStatus::STATUS_OPEN);

    if ($candidates) {
      shuffle($candidates);
      $resource = head($candidates);
    } else {
      $blueprints = DrydockBlueprint::getAllBlueprintsForResource($type);
      foreach ($blueprints as $key => $blueprint) {
        if (!$blueprint->canAllocateResources()) {
          unset($blueprints[$key]);
          continue;
        }
      }

      if (!$blueprints) {
        $lease->setStatus(DrydockLeaseStatus::STATUS_BROKEN);
        $lease->save();

        DrydockBlueprint::writeLog(
          null,
          $lease,
          "There are no resources of type '{$type}' available, and no ".
          "blueprints which can allocate new ones.");

        return;
      }

      // TODO: Rank intelligently.
      shuffle($blueprints);

      $blueprint = head($blueprints);
      $resource = $blueprint->allocateResource();
    }

    $blueprint = $resource->getBlueprint();
    $blueprint->acquireLease($resource, $lease);
  }

}


