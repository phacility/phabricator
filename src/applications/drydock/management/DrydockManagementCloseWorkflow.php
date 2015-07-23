<?php

final class DrydockManagementCloseWorkflow
  extends DrydockManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('close')
      ->setSynopsis(pht('Close a resource.'))
      ->setArguments(
        array(
          array(
            'name'      => 'ids',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $ids = $args->getArg('ids');
    if (!$ids) {
      throw new PhutilArgumentUsageException(
        pht('Specify one or more resource IDs to close.'));
    }

    $viewer = $this->getViewer();

    $resources = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();

    foreach ($ids as $id) {
      $resource = idx($resources, $id);
      if (!$resource) {
        $console->writeErr("%s\n", pht('Resource %d does not exist!', $id));
      } else if ($resource->getStatus() != DrydockResourceStatus::STATUS_OPEN) {
        $console->writeErr("%s\n", pht("Resource %d is not 'open'!", $id));
      } else {
        $resource->closeResource();
        $console->writeErr("%s\n", pht('Closed resource %d.', $id));
      }
    }

  }

}
