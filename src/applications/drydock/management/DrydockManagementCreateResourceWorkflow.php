<?php

final class DrydockManagementCreateResourceWorkflow
  extends DrydockManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('create-resource')
      ->setSynopsis(pht('Create a resource manually.'))
      ->setArguments(
        array(
          array(
            'name'      => 'name',
            'param'     => 'resource_name',
            'help'      => pht('Resource name.'),
          ),
          array(
            'name'      => 'blueprint',
            'param'     => 'blueprint_id',
            'help'      => pht('Blueprint ID.'),
          ),
          array(
            'name'      => 'attributes',
            'param'     => 'name=value,...',
            'help'      => pht('Resource attributes.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $resource_name = $args->getArg('name');
    if (!$resource_name) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a resource name with `%s`.',
          '--name'));
    }

    $blueprint_id = $args->getArg('blueprint');
    if (!$blueprint_id) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a blueprint ID with `%s`.',
          '--blueprint'));
    }

    $attributes = $args->getArg('attributes');
    if ($attributes) {
      $options = new PhutilSimpleOptions();
      $options->setCaseSensitive(true);
      $attributes = $options->parse($attributes);
    }

    $viewer = $this->getViewer();

    $blueprint = id(new DrydockBlueprintQuery())
      ->setViewer($viewer)
      ->withIDs(array($blueprint_id))
      ->executeOne();
    if (!$blueprint) {
      throw new PhutilArgumentUsageException(
        pht('Specified blueprint does not exist.'));
    }

    $resource = id(new DrydockResource())
      ->setBlueprintPHID($blueprint->getPHID())
      ->setType($blueprint->getImplementation()->getType())
      ->setName($resource_name)
      ->setStatus(DrydockResourceStatus::STATUS_OPEN);
    if ($attributes) {
      $resource->setAttributes($attributes);
    }
    $resource->save();

    $console->writeOut("%s\n", pht('Created Resource %s', $resource->getID()));
    return 0;
  }

}
