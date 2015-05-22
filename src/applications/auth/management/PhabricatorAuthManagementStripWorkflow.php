<?php

final class PhabricatorAuthManagementStripWorkflow
  extends PhabricatorAuthManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('strip')
      ->setExamples('**strip** [--user username] [--type type]')
      ->setSynopsis(pht('Remove multi-factor authentication from an account.'))
      ->setArguments(
        array(
          array(
            'name' => 'user',
            'param' => 'username',
            'repeat' => true,
            'help' => pht('Strip factors from specified users.'),
          ),
          array(
            'name' => 'all-users',
            'help' => pht('Strip factors from all users.'),
          ),
          array(
            'name' => 'type',
            'param' => 'factortype',
            'repeat' => true,
            'help' => pht('Strip a specific factor type.'),
          ),
          array(
            'name' => 'all-types',
            'help' => pht('Strip all factors, regardless of type.'),
          ),
          array(
            'name' => 'force',
            'help' => pht('Strip factors without prompting.'),
          ),
          array(
            'name' => 'dry-run',
            'help' => pht('Show factors, but do not strip them.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $usernames = $args->getArg('user');
    $all_users = $args->getArg('all-users');

    if ($usernames && $all_users) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify either specific users with %s, or all users with '.
          '%s, but not both.',
          '--user',
          '--all-users'));
    } else if (!$usernames && !$all_users) {
      throw new PhutilArgumentUsageException(
        pht(
          'Use %s to specify which user to strip factors from, or '.
          '%s to strip factors from all users.',
          '--user',
          '--all-users'));
    } else if ($usernames) {
      $users = id(new PhabricatorPeopleQuery())
        ->setViewer($this->getViewer())
        ->withUsernames($usernames)
        ->execute();

      $users_by_username = mpull($users, null, 'getUsername');
      foreach ($usernames as $username) {
        if (empty($users_by_username[$username])) {
          throw new PhutilArgumentUsageException(
            pht(
              'No user exists with username "%s".',
              $username));
        }
      }
    } else {
      $users = null;
    }

    $types = $args->getArg('type');
    $all_types = $args->getArg('all-types');
    if ($types && $all_types) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify either specific factors with --type, or all factors with '.
          '--all-types, but not both.'));
    } else if (!$types && !$all_types) {
      throw new PhutilArgumentUsageException(
        pht(
          'Use --type to specify which factor to strip, or --all-types to '.
          'strip all factors. Use `auth list-factors` to show the available '.
          'factor types.'));
    }

    if ($users && $types) {
      $factors = id(new PhabricatorAuthFactorConfig())->loadAllWhere(
        'userPHID IN (%Ls) AND factorKey IN (%Ls)',
        mpull($users, 'getPHID'),
        $types);
    } else if ($users) {
      $factors = id(new PhabricatorAuthFactorConfig())->loadAllWhere(
        'userPHID IN (%Ls)',
        mpull($users, 'getPHID'));
    } else if ($types) {
      $factors = id(new PhabricatorAuthFactorConfig())->loadAllWhere(
        'factorKey IN (%Ls)',
        $types);
    } else {
      $factors = id(new PhabricatorAuthFactorConfig())->loadAll();
    }

    if (!$factors) {
      throw new PhutilArgumentUsageException(
        pht('There are no matching factors to strip.'));
    }

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(mpull($factors, 'getUserPHID'))
      ->execute();

    $console = PhutilConsole::getConsole();

    $console->writeOut("%s\n\n", pht('These auth factors will be stripped:'));

    foreach ($factors as $factor) {
      $impl = $factor->getImplementation();
      $console->writeOut(
        "    %s\t%s\t%s\n",
        $handles[$factor->getUserPHID()]->getName(),
        $factor->getFactorKey(),
        ($impl
          ? $impl->getFactorName()
          : '?'));
    }

    $is_dry_run = $args->getArg('dry-run');
    if ($is_dry_run) {
      $console->writeOut(
        "\n%s\n",
        pht('End of dry run.'));

      return 0;
    }

    $force = $args->getArg('force');
    if (!$force) {
      if (!$console->confirm(pht('Strip these authentication factors?'))) {
        throw new PhutilArgumentUsageException(
          pht('User aborted the workflow.'));
      }
    }

    $console->writeOut("%s\n", pht('Stripping authentication factors...'));

    foreach ($factors as $factor) {
      $user = id(new PhabricatorPeopleQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs(array($factor->getUserPHID()))
        ->executeOne();

      $factor->delete();

      if ($user) {
        $user->updateMultiFactorEnrollment();
      }
    }

    $console->writeOut("%s\n", pht('Done.'));

    return 0;
  }

}
