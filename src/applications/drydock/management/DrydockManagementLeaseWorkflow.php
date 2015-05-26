<?php

final class DrydockManagementLeaseWorkflow
  extends DrydockManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('lease')
      ->setSynopsis(pht('Lease a resource.'))
      ->setArguments(
        array(
          array(
            'name'      => 'type',
            'param'     => 'resource_type',
            'help'      => pht('Resource type.'),
          ),
          array(
            'name'      => 'attributes',
            'param'     => 'name=value,...',
            'help'      => pht('Resource specficiation.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $resource_type = $args->getArg('type');
    if (!$resource_type) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a resource type with `%s`.',
          '--type'));
    }

    $attributes = $args->getArg('attributes');
    if ($attributes) {
      $options = new PhutilSimpleOptions();
      $options->setCaseSensitive(true);
      $attributes = $options->parse($attributes);
    }

    PhabricatorWorker::setRunAllTasksInProcess(true);

    $lease = id(new DrydockLease())
      ->setResourceType($resource_type);
    if ($attributes) {
      $lease->setAttributes($attributes);
    }
    $lease
      ->queueForActivation()
      ->waitUntilActive();

    $console->writeOut("%s\n", pht('Acquired Lease %s', $lease->getID()));
    return 0;
  }

}
