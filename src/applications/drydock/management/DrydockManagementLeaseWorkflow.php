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
    $viewer = $this->getViewer();

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

    $drydock_phid = id(new PhabricatorDrydockApplication())->getPHID();
    $lease->setAuthorizingPHID($drydock_phid);

    // TODO: This is not hugely scalable, although this is a debugging workflow
    // so maybe it's fine. Do we even need `bin/drydock lease` in the long run?
    $all_blueprints = id(new DrydockBlueprintQuery())
      ->setViewer($viewer)
      ->execute();
    $allowed_phids = mpull($all_blueprints, 'getPHID');
    if (!$allowed_phids) {
      throw new Exception(
        pht(
          'No blueprints exist which can plausibly allocate resources to '.
          'satisfy the requested lease.'));
    }
    $lease->setAllowedBlueprintPHIDs($allowed_phids);

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
