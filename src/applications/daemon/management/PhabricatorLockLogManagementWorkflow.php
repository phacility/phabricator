<?php

final class PhabricatorLockLogManagementWorkflow
  extends PhabricatorLockManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('log')
      ->setSynopsis(pht('Enable, disable, or show the lock log.'))
      ->setArguments(
        array(
          array(
            'name' => 'enable',
            'help' => pht('Enable the lock log.'),
          ),
          array(
            'name' => 'disable',
            'help' => pht('Disable the lock log.'),
          ),
          array(
            'name' => 'name',
            'param' => 'name',
            'help' => pht('Review logs for a specific lock.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $is_enable = $args->getArg('enable');
    $is_disable = $args->getArg('disable');

    if ($is_enable && $is_disable) {
      throw new PhutilArgumentUsageException(
        pht(
          'You can not both "--enable" and "--disable" the lock log.'));
    }

    $with_name = $args->getArg('name');

    if ($is_enable || $is_disable) {
      if (strlen($with_name)) {
        throw new PhutilArgumentUsageException(
          pht(
            'You can not both "--enable" or "--disable" with search '.
            'parameters like "--name".'));
      }

      $gc = new PhabricatorDaemonLockLogGarbageCollector();
      $is_enabled = (bool)$gc->getRetentionPolicy();

      $config_key = 'phd.garbage-collection';
      $const = $gc->getCollectorConstant();
      $value = PhabricatorEnv::getEnvConfig($config_key);

      if ($is_disable) {
        if (!$is_enabled) {
          echo tsprintf(
            "%s\n",
            pht('Lock log is already disabled.'));
          return 0;
        }
        echo tsprintf(
          "%s\n",
          pht('Disabling the lock log.'));

        unset($value[$const]);
      } else {
        if ($is_enabled) {
          echo tsprintf(
            "%s\n",
            pht('Lock log is already enabled.'));
          return 0;
        }
        echo tsprintf(
          "%s\n",
          pht('Enabling the lock log.'));

        $value[$const] = phutil_units('24 hours in seconds');
      }

      id(new PhabricatorConfigLocalSource())
        ->setKeys(
          array(
            $config_key => $value,
          ));

      echo tsprintf(
        "%s\n",
        pht('Done.'));

      echo tsprintf(
        "%s\n",
        pht('Restart daemons to apply changes.'));

      return 0;
    }

    $table = new PhabricatorDaemonLockLog();
    $conn = $table->establishConnection('r');

    $parts = array();
    if (strlen($with_name)) {
      $parts[] = qsprintf(
        $conn,
        'lockName = %s',
        $with_name);
    }

    if (!$parts) {
      $constraint = qsprintf($conn, '1 = 1');
    } else {
      $constraint = qsprintf($conn, '%LA', $parts);
    }

    $logs = $table->loadAllWhere(
      '%Q ORDER BY id DESC LIMIT 100',
      $constraint);
    $logs = array_reverse($logs);

    if (!$logs) {
      echo tsprintf(
        "%s\n",
        pht('No matching lock logs.'));
      return 0;
    }

    $table = id(new PhutilConsoleTable())
      ->setBorders(true)
      ->addColumn(
        'id',
        array(
          'title' => pht('Lock'),
        ))
      ->addColumn(
        'name',
        array(
          'title' => pht('Name'),
        ))
      ->addColumn(
        'acquired',
        array(
          'title' => pht('Acquired'),
        ))
      ->addColumn(
        'released',
        array(
          'title' => pht('Released'),
        ))
      ->addColumn(
        'held',
        array(
          'title' => pht('Held'),
        ))
      ->addColumn(
        'parameters',
        array(
          'title' => pht('Parameters'),
        ))
      ->addColumn(
        'context',
        array(
          'title' => pht('Context'),
        ));

    $viewer = $this->getViewer();

    foreach ($logs as $log) {
      $created = $log->getDateCreated();
      $released = $log->getLockReleased();

      if ($released) {
        $held = '+'.($released - $created);
      } else {
        $held = null;
      }

      $created = phabricator_datetime($created, $viewer);
      $released = phabricator_datetime($released, $viewer);

      $parameters = $log->getLockParameters();
      $context = $log->getLockContext();

      $table->addRow(
        array(
          'id' => $log->getID(),
          'name' => $log->getLockName(),
          'acquired' => $created,
          'released' => $released,
          'held' => $held,
          'parameters' => $this->flattenParameters($parameters),
          'context' => $this->flattenParameters($context),
        ));
    }

    $table->draw();

    return 0;
  }

  private function flattenParameters(array $params, $keys = true) {
    $flat = array();
    foreach ($params as $key => $value) {
      if (is_array($value)) {
        $value = $this->flattenParameters($value, false);
      }
      if ($keys) {
        $flat[] = "{$key}={$value}";
      } else {
        $flat[] = "{$value}";
      }
    }

    if ($keys) {
      $flat = implode(', ', $flat);
    } else {
      $flat = implode(' ', $flat);
    }

    return $flat;
  }

}
