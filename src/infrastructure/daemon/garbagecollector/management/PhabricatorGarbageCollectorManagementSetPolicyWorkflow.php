<?php

final class PhabricatorGarbageCollectorManagementSetPolicyWorkflow
  extends PhabricatorGarbageCollectorManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('set-policy')
      ->setExamples(
        "**set-policy** --collector __collector__ --days 30\n".
        "**set-policy** --collector __collector__ --indefinite\n".
        "**set-policy** --collector __collector__ --default")
      ->setSynopsis(
        pht(
          'Change retention policies for a garbage collector.'))
      ->setArguments(
        array(
          array(
            'name' => 'collector',
            'param' => 'const',
            'help' => pht(
              'Constant identifying the garbage collector.'),
          ),
          array(
            'name' => 'indefinite',
            'help' => pht(
              'Set an indefinite retention policy.'),
          ),
          array(
            'name' => 'default',
            'help' => pht(
              'Use the default retention policy.'),
          ),
          array(
            'name' => 'days',
            'param' => 'count',
            'help' => pht(
              'Retain data for the specified number of days.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $config_key = 'phd.garbage-collection';

    $collector = $this->getCollector($args->getArg('collector'));

    $days = $args->getArg('days');
    $indefinite = $args->getArg('indefinite');
    $default = $args->getArg('default');

    $count = 0;
    if ($days !== null) {
      $count++;
    }
    if ($indefinite) {
      $count++;
    }
    if ($default) {
      $count++;
    }

    if (!$count) {
      throw new PhutilArgumentUsageException(
        pht(
          'Choose a policy with "%s", "%s" or "%s".',
          '--days',
          '--indefinite',
          '--default'));
    }

    if ($count > 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Options "%s", "%s" and "%s" represent mutually exclusive ways '.
          'to choose a policy. Specify only one.',
          '--days',
          '--indefinite',
          '--default'));
    }

    if ($days !== null) {
      $days = (int)$days;
      if ($days < 1) {
        throw new PhutilArgumentUsageException(
          pht(
            'Specify a positive number of days to retain data for.'));
      }
    }

    $collector_const = $collector->getCollectorConstant();
    $value = PhabricatorEnv::getEnvConfig($config_key);

    if ($days !== null) {
      echo tsprintf(
        "%s\n",
        pht(
          'Setting retention policy for "%s" to %s day(s).',
          $collector->getCollectorName(),
          new PhutilNumber($days)));

      $value[$collector_const] = phutil_units($days.' days in seconds');
    } else if ($indefinite) {
      echo tsprintf(
        "%s\n",
        pht(
          'Setting "%s" to be retained indefinitely.',
          $collector->getCollectorName()));

      $value[$collector_const] = null;
    } else {
      echo tsprintf(
        "%s\n",
        pht(
          'Restoring "%s" to the default retention policy.',
          $collector->getCollectorName()));

      unset($value[$collector_const]);
    }

    id(new PhabricatorConfigLocalSource())
      ->setKeys(
        array(
          $config_key => $value,
        ));

    echo tsprintf(
      "%s\n",
      pht(
        'Wrote new policy to local configuration.'));

    echo tsprintf(
      "%s\n",
      pht(
        'This change will take effect the next time the daemons are '.
        'restarted.'));

    return 0;
  }

}
