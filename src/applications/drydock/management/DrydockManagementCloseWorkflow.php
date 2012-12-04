<?php

final class DrydockManagementCloseWorkflow
  extends DrydockManagementWorkflow {

  public function didConstruct() {
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
        "Specify one or more resource IDs to close.");
    }

    foreach ($ids as $id) {
      $resource = id(new DrydockResource())->load($id);
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
