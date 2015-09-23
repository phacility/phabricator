<?php

final class DrydockManagementUpdateResourceWorkflow
  extends DrydockManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('update-resource')
      ->setSynopsis(pht('Update a resource.'))
      ->setArguments(
        array(
          array(
            'name' => 'id',
            'param' => 'id',
            'repeat' => true,
            'help' => pht('Resource ID to update.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $ids = $args->getArg('id');
    if (!$ids) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify one or more resource IDs to update with "%s".',
          '--id'));
    }

    $resources = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();

    PhabricatorWorker::setRunAllTasksInProcess(true);

    foreach ($ids as $id) {
      $resource = idx($resources, $id);

      if (!$resource) {
        echo tsprintf(
          "%s\n",
          pht('Resource "%s" does not exist.', $id));
        continue;
      }

      echo tsprintf(
        "%s\n",
        pht('Updating resource "%s".', $id));

      $resource->scheduleUpdate();
    }

  }

}
