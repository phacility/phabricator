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
            'param'     => 'blueprint_id',
            'help'      => 'Blueprint ID.',
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

    $blueprint_id = $args->getArg('blueprint');
    if (!$blueprint_id) {
      throw new PhutilArgumentUsageException(
        "Specify a blueprint ID with `--blueprint`.");
    }

    $attributes = $args->getArg('attributes');
    if ($attributes) {
      $options = new PhutilSimpleOptions();
      $options->setCaseSensitive(true);
      $attributes = $options->parse($attributes);
    }

    $blueprint = id(new DrydockBlueprint())->load((int)$blueprint_id);
    if (!$blueprint) {
      throw new PhutilArgumentUsageException(
        "Specified blueprint does not exist.");
    }

    $resource = new DrydockResource();
    $resource->setBlueprintPHID($blueprint->getPHID());
    $resource->setType($blueprint->getImplementation()->getType());
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
