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

final class DrydockManagementLeaseWorkflow
  extends DrydockManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('lease')
      ->setSynopsis('Lease a resource.')
      ->setArguments(
        array(
          array(
            'name'      => 'type',
            'param'     => 'resource_type',
            'help'      => 'Resource type.',
          ),
          array(
            'name'      => 'attributes',
            'param'     => 'name=value,...',
            'help'      => 'Resource specficiation.',
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $resource_type = $args->getArg('type');
    if (!$resource_type) {
      throw new PhutilArgumentUsageException(
        "Specify a resource type with `--type`.");
    }

    $attributes = $args->getArg('attributes');
    if ($attributes) {
      $options = new PhutilSimpleOptions();
      $attributes = $options->parse($attributes);
    }

    $lease = new DrydockLease();
    $lease->setResourceType($resource_type);
    if ($attributes) {
      $lease->setAttributes($attributes);
    }
    $lease->queueForActivation();

    $root = dirname(phutil_get_library_root('phabricator'));
    $wait = new ExecFuture(
      'php -f %s wait-for-lease --id %s',
      $root.'/scripts/drydock/drydock_control.php',
      $lease->getID());

    $cursor = 0;
    foreach (Futures(array($wait))->setUpdateInterval(1) as $key => $future) {
      if ($future) {
        $future->resolvex();
        break;
      }

      $logs = id(new DrydockLogQuery())
        ->withLeaseIDs(array($lease->getID()))
        ->withAfterID($cursor)
        ->setOrder(DrydockLogQuery::ORDER_ID)
        ->execute();

      if ($logs) {
        foreach ($logs as $log) {
          $console->writeErr("%s\n", $log->getMessage());
        }
        $cursor = max(mpull($logs, 'getID'));
      }
    }

    $console->writeOut("Acquired Lease %s\n", $lease->getID());
    return 0;
  }

}
