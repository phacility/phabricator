<?php

final class DrydockManagementUpdateLeaseWorkflow
  extends DrydockManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('update-lease')
      ->setSynopsis(pht('Update a lease.'))
      ->setArguments(
        array(
          array(
            'name' => 'id',
            'param' => 'id',
            'repeat' => true,
            'help' => pht('Lease ID to update.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $ids = $args->getArg('id');
    if (!$ids) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify one or more lease IDs to update with "%s".',
          '--id'));
    }

    $leases = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();

    PhabricatorWorker::setRunAllTasksInProcess(true);

    foreach ($ids as $id) {
      $lease = idx($leases, $id);

      if (!$lease) {
        echo tsprintf(
          "%s\n",
          pht('Lease "%s" does not exist.', $id));
        continue;
      }

      echo tsprintf(
        "%s\n",
        pht('Updating lease "%s".', $id));

      $lease->scheduleUpdate();
    }
  }

}
