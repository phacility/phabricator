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

final class DrydockManagementWaitForLeaseWorkflow
  extends DrydockManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('wait-for-lease')
      ->setSynopsis('Wait for a lease to become available.')
      ->setArguments(
        array(
          array(
            'name'      => 'id',
            'param'     => 'lease_id',
            'help'      => 'Lease ID to wait for.',
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $lease_id = $args->getArg('id');
    if (!$lease_id) {
      throw new PhutilArgumentUsageException(
        "Specify a lease ID with `--id`.");
    }

    $console = PhutilConsole::getConsole();

    $lease = id(new DrydockLease())->load($lease_id);
    if (!$lease) {
      $console->writeErr("No such lease.\n");
      return 1;
    } else {
      $lease->waitUntilActive();
      $console->writeErr("Lease active.\n");
      return 0;
    }
  }

}
