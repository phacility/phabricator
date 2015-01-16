<?php

final class DrydockManagementCloseWorkflow
  extends DrydockManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('close')
      ->setSynopsis('Close a resource.')
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
        'Specify one or more resource IDs to close.');
    }

    $viewer = $this->getViewer();

    $resources = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();

    foreach ($ids as $id) {
      $resource = idx($resources, $id);
      if (!$resource) {
        $console->writeErr("Resource %d does not exist!\n", $id);
      } else if ($resource->getStatus() != DrydockResourceStatus::STATUS_OPEN) {
        $console->writeErr("Resource %d is not 'open'!\n", $id);
      } else {
        $resource->closeResource();
        $console->writeErr("Closed resource %d.\n", $id);
      }
    }

  }

}
