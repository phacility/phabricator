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
            'name' => 'until',
            'param' => 'time',
            'help' => pht('Set lease expiration time.'),
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

    $until = $args->getArg('until');
    if (strlen($until)) {
      $until = strtotime($until);
      if ($until <= 0) {
        throw new PhutilArgumentUsageException(
          pht(
            'Unable to parse argument to "%s".',
            '--until'));
      }
    }

    $attributes = $args->getArg('attributes');
    if ($attributes) {
      $options = new PhutilSimpleOptions();
      $options->setCaseSensitive(true);
      $attributes = $options->parse($attributes);
    }

    $lease = id(new DrydockLease())
      ->setResourceType($resource_type);

    if ($attributes) {
      $lease->setAttributes($attributes);
    }

    if ($until) {
      $lease->setUntil($until);
    }

    $lease->queueForActivation();

    echo tsprintf(
      "%s\n",
      pht('Waiting for daemons to activate lease...'));

    $lease->waitUntilActive();

    echo tsprintf(
      "%s\n",
      pht('Activated lease "%s".', $lease->getID()));

    return 0;
  }

}
