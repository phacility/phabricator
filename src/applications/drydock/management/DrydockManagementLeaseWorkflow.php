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
            'name'      => 'spec',
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

    $spec = $args->getArg('spec');
    if ($spec) {
      $options = new PhutilSimpleOptions();
      $spec = $options->parse($spec);
    }

    $allocator = new DrydockAllocator();
    $allocator->setResourceType($resource_type);
    if ($spec) {
      // TODO: Shove this in there.
    }

    $lease = $allocator->allocate();

    $root = dirname(phutil_get_library_root('phabricator'));
    $wait = new ExecFuture(
      'php -f %s wait-for-lease --id %s',
      $root.'/scripts/drydock/drydock_control.php',
      $lease->getID());

    foreach (Futures(array($wait))->setUpdateInterval(1) as $key => $future) {
      if ($future) {
        $future->resolvex();
        break;
      }

      // TODO: Pull logs.
      $console->writeErr("Working...\n");
    }

    $console->writeOut("Acquired Lease %s\n", $lease->getID());
    return 0;
  }

}
