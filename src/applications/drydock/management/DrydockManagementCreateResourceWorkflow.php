<?php

final class DrydockManagementCreateResourceWorkflow
  extends DrydockManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('create-resource')
      ->setSynopsis('Create a resource manually.')
      ->setArguments(
        array(
          array(
            'name'      => 'name',
            'param'     => 'resource_name',
            'help'      => 'Resource name.',
          ),
          array(
            'name'      => 'blueprint',
            'param'     => 'blueprint_type',
            'help'      => 'Blueprint type.',
          ),
          array(
            'name'      => 'attributes',
            'param'     => 'name=value,...',
            'help'      => 'Resource attributes.',
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $resource_name = $args->getArg('name');
    if (!$resource_name) {
      throw new PhutilArgumentUsageException(
        "Specify a resource name with `--name`.");
    }

    $blueprint_type = $args->getArg('blueprint');
    if (!$blueprint_type) {
      throw new PhutilArgumentUsageException(
        "Specify a blueprint type with `--blueprint`.");
    }

    $attributes = $args->getArg('attributes');
    if ($attributes) {
      $options = new PhutilSimpleOptions();
      $options->setCaseSensitive(true);
      $attributes = $options->parse($attributes);
    }

    $resource = new DrydockResource();
    $resource->setBlueprintClass($blueprint_type);
    $resource->setType(id(new $blueprint_type())->getType());
    $resource->setName($resource_name);
    $resource->setStatus(DrydockResourceStatus::STATUS_OPEN);
    if ($attributes) {
      $resource->setAttributes($attributes);
    }
    $resource->save();

    $console->writeOut("Created Resource %s\n", $resource->getID());
    return 0;
  }

}
