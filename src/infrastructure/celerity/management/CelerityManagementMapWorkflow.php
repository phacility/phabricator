<?php

final class CelerityManagementMapWorkflow
  extends CelerityManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('map')
      ->setExamples('**map** [options]')
      ->setSynopsis(pht('Rebuild static resource maps.'))
      ->setArguments(
        array());
  }

  public function execute(PhutilArgumentParser $args) {
    $resources_map = CelerityResources::getAll();

    foreach ($resources_map as $name => $resources) {
      // TODO: This does not do anything useful yet.
      var_dump($resources->findBinaryResources());
      var_dump($resources->findTextResources());
    }

    return 0;
  }

}
