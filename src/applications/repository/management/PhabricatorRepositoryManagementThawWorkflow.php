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
            'param' => 'device|service',
            'help' => pht(
              'Demote a device (or all devices in a service) discarding '.
              'unsynchronized changes. Clears stuck write locks and recovers '.
              'from lost leaders.'),
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
            'name' => 'all-repositories',
            'help' => pht(
              'Apply the promotion or demotion to all repositories hosted '.
              'on the device.'),
          ),
          array(
            'name' => 'repositories',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

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

    $target_name = nonempty($promote, $demote);

    $devices = id(new AlmanacDeviceQuery())
      ->setViewer($viewer)
      ->withNames(array($target_name))
      ->execute();
    if (!$devices) {
      $service = id(new AlmanacServiceQuery())
        ->setViewer($viewer)
        ->withNames(array($target_name))
        ->executeOne();

      if (!$service) {
        throw new PhutilArgumentUsageException(
          pht('No device or service named "%s" exists.', $target_name));
      }

      if ($promote) {
        throw new PhutilArgumentUsageException(
          pht(
            'You can not "--promote" an entire service ("%s"). Only a single '.
            'device may be promoted.',
            $target_name));
      }

      $bindings = id(new AlmanacBindingQuery())
        ->setViewer($viewer)
        ->withServicePHIDs(array($service->getPHID()))
        ->execute();
      if (!$bindings) {
        throw new PhutilArgumentUsageException(
          pht(
            'Service "%s" is not bound to any devices.',
            $target_name));
      }

      $interfaces = id(new AlmanacInterfaceQuery())
        ->setViewer($viewer)
        ->withPHIDs(mpull($bindings, 'getInterfacePHID'))
        ->execute();

      $device_phids = mpull($interfaces, 'getDevicePHID');

      $devices = id(new AlmanacDeviceQuery())
        ->setViewer($viewer)
        ->withPHIDs($device_phids)
        ->execute();
    }

    $repository_names = $args->getArg('repositories');
    $all_repositories = $args->getArg('all-repositories');
    if ($repository_names && $all_repositories) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a list of repositories or "--all-repositories", '.
          'but not both.'));
    } else if (!$repository_names && !$all_repositories) {
      throw new PhutilArgumentUsageException(
        pht(
          'Select repositories to affect by providing a list of repositories '.
          'or using the "--all-repositories" flag.'));
    }

    if ($repository_names) {
      $repositories = $this->loadRepositories($args, 'repositories');
      if (!$repositories) {
        throw new PhutilArgumentUsageException(
          pht('Specify one or more repositories to thaw.'));
      }
    } else {
      $repositories = array();

      $services = id(new AlmanacServiceQuery())
        ->setViewer($viewer)
        ->withDevicePHIDs(mpull($devices, 'getPHID'))
        ->execute();
      if ($services) {
        $repositories = id(new PhabricatorRepositoryQuery())
          ->setViewer($viewer)
          ->withAlmanacServicePHIDs(mpull($services, 'getPHID'))
          ->execute();
      }

      if (!$repositories) {
        throw new PhutilArgumentUsageException(
          pht('There are no repositories on the selected device or service.'));
      }
    }

    $display_list = new PhutilConsoleList();
    foreach ($repositories as $repository) {
      $display_list->addItem(
        pht(
          '%s %s',
          $repository->getMonogram(),
          $repository->getName()));
    }

    echo tsprintf(
      "%s\n\n%B\n",
      pht('These repositories will be thawed:'),
      $display_list->drawConsoleString());

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
    $prompt = pht('Accept the possibility of permanent data loss?');
    if (!$is_force && !phutil_console_confirm($prompt)) {
      throw new PhutilArgumentUsageException(
        pht('User aborted the workflow.'));
    }

    foreach ($devices as $device) {
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

          if ($promote) {
            // You can only promote active devices. (You may demote active or
            // inactive devices.)
            $bindings = $service->getActiveBindings();
            $bindings = mpull($bindings, null, 'getDevicePHID');
            if (empty($bindings[$device->getPHID()])) {
              throw new PhutilArgumentUsageException(
                pht(
                  'Repository "%s" has no active binding to device "%s". '.
                  'Only actively bound devices can be promoted.',
                  $repository->getDisplayName(),
                  $device->getName()));
            }

            $versions = PhabricatorRepositoryWorkingCopyVersion::loadVersions(
              $repository->getPHID());
            $versions = mpull($versions, null, 'getDevicePHID');

            // Before we promote, make sure there are no outstanding versions
            // on devices with inactive bindings. If there are, you need to
            // demote these first.
            $inactive = array();
            foreach ($versions as $device_phid => $version) {
              if (isset($bindings[$device_phid])) {
                continue;
              }
              $inactive[$device_phid] = $version;
            }

            if ($inactive) {
              $handles = $viewer->loadHandles(array_keys($inactive));

              $handle_list = iterator_to_array($handles);
              $handle_list = mpull($handle_list, 'getName');
              $handle_list = implode(', ', $handle_list);

              throw new PhutilArgumentUsageException(
                pht(
                  'Repository "%s" has versions on inactive devices. Demote '.
                  '(or reactivate) these devices before promoting a new '.
                  'leader: %s.',
                  $repository->getDisplayName(),
                  $handle_list));
            }

            // Now, make sure there are no outstanding versions on devices with
            // active bindings. These also need to be demoted (or promoting is
            // a mistake or already happened).
            $active = array_select_keys($versions, array_keys($bindings));
            if ($active) {
              $handles = $viewer->loadHandles(array_keys($active));

              $handle_list = iterator_to_array($handles);
              $handle_list = mpull($handle_list, 'getName');
              $handle_list = implode(', ', $handle_list);

              throw new PhutilArgumentUsageException(
                pht(
                  'Unable to promote "%s" for repository "%s" because this '.
                  'cluster already has one or more unambiguous leaders: %s.',
                  $device->getName(),
                  $repository->getDisplayName(),
                  $handle_list));
            }

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
    }

    return 0;
  }

}
