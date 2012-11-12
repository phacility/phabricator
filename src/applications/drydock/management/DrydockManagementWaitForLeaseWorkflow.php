<?php

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
