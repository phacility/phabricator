<?php

final class PhabricatorRepositoryManagementThawWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('thaw')
      ->setExamples('**thaw** [options] __repository__ ...')
      ->setSynopsis(
        pht(
          'Resolve issues with frozen cluster repositories. Very advanced '.
          'and dangerous.'))
      ->setArguments(
        array(
          array(
            'name' => 'demote',
            'param' => 'device',
            'help' => pht(
              'Demote a device, discarding local changes. Clears stuck '.
              'write locks and recovers from lost leaders.'),
          ),
          array(
            'name' => 'promote',
            'param' => 'device',
            'help' => pht(
              'Promote a device, discarding changes on other devices. '.
              'Resolves ambiguous leadership and recovers from demotion '.
              'mistakes.'),
          ),
          array(
            'name' => 'force',
            'help' => pht('Run operations without asking for confirmation.'),
          ),
          array(
            'name' => 'repositories',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $repositories = $this->loadRepositories($args, 'repositories');
    if (!$repositories) {
      throw new PhutilArgumentUsageException(
        pht('Specify one or more repositories to thaw.'));
    }

    $promote = $args->getArg('promote');
    $demote = $args->getArg('demote');

    if (!$promote && !$demote) {
      throw new PhutilArgumentUsageException(
        pht('You must choose a device to --promote or --demote.'));
    }

    if ($promote && $demote) {
      throw new PhutilArgumentUsageException(
        pht('Specify either --promote or --demote, but not both.'));
    }

    $device_name = nonempty($promote, $demote);

    $device = id(new AlmanacDeviceQuery())
      ->setViewer($viewer)
      ->withNames(array($device_name))
      ->executeOne();
    if (!$device) {
      throw new PhutilArgumentUsageException(
        pht('No device "%s" exists.', $device_name));
    }

    if ($promote) {
      $risk_message = pht(
        'Promoting a device can cause the loss of any repository data which '.
        'only exists on other devices. The version of the repository on the '.
        'promoted device will become authoritative.');
    } else {
      $risk_message = pht(
        'Demoting a device can cause the loss of any repository data which '.
        'only exists on the demoted device. The version of the repository '.
        'on some other device will become authoritative.');
    }

    echo tsprintf(
      "**<bg:red> %s </bg>** %s\n",
      pht('DATA AT RISK'),
      $risk_message);

    $is_force = $args->getArg('force');
    $prompt = pht('Accept the possibilty of permanent data loss?');
    if (!$is_force && !phutil_console_confirm($prompt)) {
      throw new PhutilArgumentUsageException(
        pht('User aborted the workflow.'));
    }

    foreach ($repositories as $repository) {
      $repository_phid = $repository->getPHID();

      $write_lock = PhabricatorRepositoryWorkingCopyVersion::getWriteLock(
        $repository_phid);

      echo tsprintf(
        "%s\n",
        pht(
          'Waiting to acquire write lock for "%s"...',
          $repository->getDisplayName()));

      $write_lock->lock(phutil_units('5 minutes in seconds'));
      try {

        $service = $repository->loadAlmanacService();
        if (!$service) {
          throw new PhutilArgumentUsageException(
            pht(
              'Repository "%s" is not a cluster repository: it is not '.
              'bound to an Almanac service.',
              $repository->getDisplayName()));
        }

        $bindings = $service->getActiveBindings();
        $bindings = mpull($bindings, null, 'getDevicePHID');
        if (empty($bindings[$device->getPHID()])) {
          throw new PhutilArgumentUsageException(
            pht(
              'Repository "%s" has no active binding to device "%s". Only '.
              'actively bound devices can be promoted or demoted.',
              $repository->getDisplayName(),
              $device->getName()));
        }

        $versions = PhabricatorRepositoryWorkingCopyVersion::loadVersions(
          $repository->getPHID());

        $versions = mpull($versions, null, 'getDevicePHID');
        $versions = array_select_keys($versions, array_keys($bindings));

        if ($versions && $promote) {
          throw new PhutilArgumentUsageException(
            pht(
              'Unable to promote "%s" for repository "%s": the leaders for '.
              'this cluster are not ambiguous.',
              $device->getName(),
              $repository->getDisplayName()));
        }

        if ($promote) {
          PhabricatorRepositoryWorkingCopyVersion::updateVersion(
            $repository->getPHID(),
            $device->getPHID(),
            0);

          echo tsprintf(
            "%s\n",
            pht(
              'Promoted "%s" to become a leader for "%s".',
              $device->getName(),
              $repository->getDisplayName()));
        }

        if ($demote) {
          PhabricatorRepositoryWorkingCopyVersion::demoteDevice(
            $repository->getPHID(),
            $device->getPHID());

          echo tsprintf(
            "%s\n",
            pht(
              'Demoted "%s" from leadership of repository "%s".',
              $device->getName(),
              $repository->getDisplayName()));
        }
      } catch (Exception $ex) {
        $write_lock->unlock();
        throw $ex;
      }

      $write_lock->unlock();
    }

    return 0;
  }

}
