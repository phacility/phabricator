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
            'name' => 'attributes',
            'param' => 'file',
            'help' => pht(
              'JSON file with lease attributes. Use "-" to read attributes '.
              'from stdin.'),
          ),
          array(
            'name' => 'count',
            'param' => 'N',
            'default' => 1,
            'help' => pht('Lease a given number of identical resources.'),
          ),
          array(
            'name' => 'blueprint',
            'param' => 'identifier',
            'repeat' => true,
            'help' => pht('Lease resources from a specific blueprint.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $resource_type = $args->getArg('type');
    if (!phutil_nonempty_string($resource_type)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a resource type with "--type".'));
    }

    $until = $args->getArg('until');
    if (phutil_nonempty_string($until)) {
      $until = strtotime($until);
      if ($until <= 0) {
        throw new PhutilArgumentUsageException(
          pht(
            'Unable to parse argument to "--until".'));
      }
    }

    $count = $args->getArgAsInteger('count');
    if ($count < 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Value provided to "--count" must be a nonzero, positive '.
          'number.'));
    }

    $attributes_file = $args->getArg('attributes');
    if (phutil_nonempty_string($attributes_file)) {
      if ($attributes_file == '-') {
        echo tsprintf(
          "%s\n",
          pht('Reading JSON attributes from stdin...'));
        $data = file_get_contents('php://stdin');
      } else {
        $data = Filesystem::readFile($attributes_file);
      }

      $attributes = phutil_json_decode($data);
    } else {
      $attributes = array();
    }

    $filter_identifiers = $args->getArg('blueprint');
    if ($filter_identifiers) {
      $filter_blueprints = $this->getBlueprintFilterMap($filter_identifiers);
    } else {
      $filter_blueprints = array();
    }

    $blueprint_phids = null;

    $leases = array();
    for ($idx = 0; $idx < $count; $idx++) {
      $lease = id(new DrydockLease())
        ->setResourceType($resource_type);

      $drydock_phid = id(new PhabricatorDrydockApplication())->getPHID();
      $lease->setAuthorizingPHID($drydock_phid);

      if ($attributes) {
        $lease->setAttributes($attributes);
      }

      if ($blueprint_phids === null) {
        $blueprint_phids = $this->newAllowedBlueprintPHIDs(
          $lease,
          $filter_blueprints);
      }

      $lease->setAllowedBlueprintPHIDs($blueprint_phids);

      if ($until) {
        $lease->setUntil($until);
      }

      // If something fatals or the user interrupts the process (for example,
      // with "^C"), release the lease. We'll cancel this below, if the lease
      // actually activates.
      $lease->setReleaseOnDestruction(true);

      $leases[] = $lease;
    }

    // TODO: This would probably be better handled with PhutilSignalRouter,
    // but it currently doesn't route SIGINT. We're initializing it to setup
    // SIGTERM handling and make eventual migration easier.
    $router = PhutilSignalRouter::getRouter();
    pcntl_signal(SIGINT, array($this, 'didReceiveInterrupt'));

    $t_start = microtime(true);


    echo tsprintf(
      "%s\n\n",
      pht('Leases queued for activation:'));

    foreach ($leases as $lease) {
      $lease->queueForActivation();

      echo tsprintf(
        "        __%s__\n",
        PhabricatorEnv::getProductionURI($lease->getURI()));
    }

    echo tsprintf(
      "\n%s\n\n",
      pht('Waiting for daemons to activate leases...'));

    foreach ($leases as $lease) {
      $this->waitUntilActive($lease);
    }

    // Now that we've survived activation and the lease is good, make it
    // durable.
    foreach ($leases as $lease) {
      $lease->setReleaseOnDestruction(false);
    }

    $t_end = microtime(true);

    echo tsprintf(
      "\n%s\n\n",
      pht(
        'Activation complete. Leases are permanent until manually '.
        'released with:'));

    foreach ($leases as $lease) {
      echo tsprintf(
        "    %s\n",
        pht('$ ./bin/drydock release-lease --id %d', $lease->getID()));
    }

    echo tsprintf(
      "\n%s\n",
      pht(
        'Leases activated in %sms.',
        new PhutilNumber((int)(($t_end - $t_start) * 1000))));

    return 0;
  }

  public function didReceiveInterrupt($signo) {
    // Doing this makes us run destructors, particularly the "release on
    // destruction" trigger on the lease.
    exit(128 + $signo);
  }

  private function waitUntilActive(DrydockLease $lease) {
    $viewer = $this->getViewer();

    $log_cursor = 0;
    $log_types = DrydockLogType::getAllLogTypes();

    $is_active = false;
    while (!$is_active) {
      $lease->reload();

      $pager = id(new AphrontCursorPagerView())
        ->setBeforeID($log_cursor);

      // While we're waiting, show the user any logs which the daemons have
      // generated to give them some clue about what's going on.
      $logs = id(new DrydockLogQuery())
        ->setViewer($viewer)
        ->withLeasePHIDs(array($lease->getPHID()))
        ->executeWithCursorPager($pager);
      if ($logs) {
        $logs = mpull($logs, null, 'getID');
        ksort($logs);
        $log_cursor = last_key($logs);
      }

      foreach ($logs as $log) {
        $type_key = $log->getType();
        if (isset($log_types[$type_key])) {
          $type_object = id(clone $log_types[$type_key])
            ->setLog($log)
            ->setViewer($viewer);

          $log_data = $log->getData();

          $type = $type_object->getLogTypeName();
          $data = $type_object->renderLogForText($log_data);
        } else {
          $type = pht('Unknown ("%s")', $type_key);
          $data = null;
        }

        echo tsprintf(
          "(Lease #%d) <%s> %B\n",
          $lease->getID(),
          $type,
          $data);
      }

      $status = $lease->getStatus();

      switch ($status) {
        case DrydockLeaseStatus::STATUS_ACTIVE:
          $is_active = true;
          break;
        case DrydockLeaseStatus::STATUS_RELEASED:
          throw new Exception(pht('Lease has already been released!'));
        case DrydockLeaseStatus::STATUS_DESTROYED:
          throw new Exception(pht('Lease has already been destroyed!'));
        case DrydockLeaseStatus::STATUS_BROKEN:
          throw new Exception(pht('Lease has been broken!'));
        case DrydockLeaseStatus::STATUS_PENDING:
        case DrydockLeaseStatus::STATUS_ACQUIRED:
          break;
        default:
          throw new Exception(
            pht(
              'Lease has unknown status "%s".',
              $status));
      }

      if ($is_active) {
        break;
      } else {
        sleep(1);
      }
    }
  }

  private function getBlueprintFilterMap(array $identifiers) {
    $viewer = $this->getViewer();

    $query = id(new DrydockBlueprintQuery())
      ->setViewer($viewer)
      ->withIdentifiers($identifiers);

    $blueprints = $query->execute();
    $blueprints = mpull($blueprints, null, 'getPHID');

    $map = $query->getIdentifierMap();

    $seen = array();
    foreach ($identifiers as $identifier) {
      if (!isset($map[$identifier])) {
        throw new PhutilArgumentUsageException(
          pht(
            'Blueprint "%s" could not be loaded. Try a blueprint ID or '.
            'PHID.',
            $identifier));
      }

      $blueprint = $map[$identifier];

      $blueprint_phid = $blueprint->getPHID();
      if (isset($seen[$blueprint_phid])) {
        throw new PhutilArgumentUsageException(
          pht(
            'Blueprint "%s" is specified more than once (as "%s" and "%s").',
            $blueprint->getBlueprintName(),
            $seen[$blueprint_phid],
            $identifier));
      }

      $seen[$blueprint_phid] = true;
    }

    return mpull($map, null, 'getPHID');
  }

  private function newAllowedBlueprintPHIDs(
    DrydockLease $lease,
    array $filter_blueprints) {
    assert_instances_of($filter_blueprints, 'DrydockBlueprint');

    $viewer = $this->getViewer();

    $impls = DrydockBlueprintImplementation::getAllForAllocatingLease($lease);

    if (!$impls) {
      throw new PhutilArgumentUsageException(
        pht(
          'No known blueprint class can ever allocate the specified '.
          'lease. Check that the resource type is spelled correctly.'));
    }

    $classes = array_keys($impls);

    $blueprints = id(new DrydockBlueprintQuery())
      ->setViewer($viewer)
      ->withBlueprintClasses($classes)
      ->withDisabled(false)
      ->execute();

    if (!$blueprints) {
      throw new PhutilArgumentUsageException(
        pht(
          'No enabled blueprints exist with a blueprint class that can '.
          'plausibly allocate resources to satisfy the requested lease.'));
    }

    $phids = mpull($blueprints, 'getPHID');

    if ($filter_blueprints) {
      $allowed_map = array_fuse($phids);
      $filter_map = mpull($filter_blueprints, null, 'getPHID');

      foreach ($filter_map as $filter_phid => $blueprint) {
        if (!isset($allowed_map[$filter_phid])) {
          throw new PhutilArgumentUsageException(
            pht(
              'Specified blueprint "%s" is not capable of satisfying the '.
              'configured lease.',
              $blueprint->getBlueprintName()));
        }
      }

      $phids = mpull($filter_blueprints, 'getPHID');
    }

    return $phids;
  }

}
