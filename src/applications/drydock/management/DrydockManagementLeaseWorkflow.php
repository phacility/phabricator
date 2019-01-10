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

    $attributes_file = $args->getArg('attributes');
    if (strlen($attributes_file)) {
      if ($attributes_file == '-') {
        echo tsprintf(
          "%s\n",
          'Reading JSON attributes from stdin...');
        $data = file_get_contents('php://stdin');
      } else {
        $data = Filesystem::readFile($attributes_file);
      }

      $attributes = phutil_json_decode($data);
    } else {
      $attributes = array();
    }

    $lease = id(new DrydockLease())
      ->setResourceType($resource_type);

    $drydock_phid = id(new PhabricatorDrydockApplication())->getPHID();
    $lease->setAuthorizingPHID($drydock_phid);

    if ($attributes) {
      $lease->setAttributes($attributes);
    }

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

    if ($until) {
      $lease->setUntil($until);
    }

    // If something fatals or the user interrupts the process (for example,
    // with "^C"), release the lease. We'll cancel this below, if the lease
    // actually activates.
    $lease->setReleaseOnDestruction(true);

    // TODO: This would probably be better handled with PhutilSignalRouter,
    // but it currently doesn't route SIGINT. We're initializing it to setup
    // SIGTERM handling and make eventual migration easier.
    $router = PhutilSignalRouter::getRouter();
    pcntl_signal(SIGINT, array($this, 'didReceiveInterrupt'));

    $t_start = microtime(true);
    $lease->queueForActivation();

    echo tsprintf(
      "%s\n\n        __%s__\n\n%s\n",
      pht('Queued lease for activation:'),
      PhabricatorEnv::getProductionURI($lease->getURI()),
      pht('Waiting for daemons to activate lease...'));

    $this->waitUntilActive($lease);

    // Now that we've survived activation and the lease is good, make it
    // durable.
    $lease->setReleaseOnDestruction(false);
    $t_end = microtime(true);

    echo tsprintf(
      "%s\n\n        %s\n\n%s\n",
      pht(
        'Activation complete. This lease is permanent until manually '.
        'released with:'),
      pht('$ ./bin/drydock release-lease --id %d', $lease->getID()),
      pht(
        'Lease activated in %sms.',
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

      // While we're waiting, show the user any logs which the daemons have
      // generated to give them some clue about what's going on.
      $logs = id(new DrydockLogQuery())
        ->setViewer($viewer)
        ->withLeasePHIDs(array($lease->getPHID()))
        ->setBeforeID($log_cursor)
        ->execute();
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
          "<%s> %B\n",
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

}
