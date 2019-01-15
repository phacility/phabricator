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
            'help' => pht(
              'Strip a specific factor type. Use `bin/auth list-factors` for '.
              'a list of factor types.'),
          ),
          array(
            'name' => 'all-types',
            'help' => pht('Strip all factors, regardless of type.'),
          ),
          array(
            'name' => 'provider',
            'param' => 'phid',
            'repeat' => true,
            'help' => pht(
              'Strip factors for a specific provider. Use '.
              '`bin/auth list-mfa-providers` for a list of providers.'),
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
    $viewer = $this->getViewer();

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
          'Use "--user <username>" to specify which user to strip factors '.
          'from, or "--all-users" to strip factors from all users.'));
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
    $provider_phids = $args->getArg('provider');
    $all_types = $args->getArg('all-types');
    if ($types && $all_types) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify either specific factors with "--type", or all factors with '.
          '"--all-types", but not both.'));
    } else if ($provider_phids && $all_types) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify either specific factors with "--provider", or all factors '.
          'with "--all-types", but not both.'));
    } else if (!$types && !$all_types && !$provider_phids) {
      throw new PhutilArgumentUsageException(
        pht(
          'Use "--type <type>" or "--provider <phid>" to specify which '.
          'factors to strip, or "--all-types" to strip all factors. '.
          'Use `bin/auth list-factors` to show the available factor types '.
          'or `bin/auth list-mfa-providers` to show available providers.'));
    }

    $type_map = PhabricatorAuthFactor::getAllFactors();

    if ($types) {
      foreach ($types as $type) {
        if (!isset($type_map[$type])) {
          throw new PhutilArgumentUsageException(
            pht(
              'Factor type "%s" is unknown. Use `bin/auth list-factors` to '.
              'get a list of known factor types.',
              $type));
        }
      }
    }

    $provider_query = id(new PhabricatorAuthFactorProviderQuery())
      ->setViewer($viewer);

    if ($provider_phids) {
      $provider_query->withPHIDs($provider_phids);
    }

    if ($types) {
      $provider_query->withProviderFactorKeys($types);
    }

    $providers = $provider_query->execute();
    $providers = mpull($providers, null, 'getPHID');

    if ($provider_phids) {
      foreach ($provider_phids as $provider_phid) {
        if (!isset($providers[$provider_phid])) {
          throw new PhutilArgumentUsageException(
            pht(
              'No provider with PHID "%s" exists. '.
              'Use `bin/auth list-mfa-providers` to list providers.',
              $provider_phid));
        }
      }
    } else {
      if (!$providers) {
        throw new PhutilArgumentUsageException(
          pht(
            'There are no configured multi-factor providers.'));
      }
    }

    $factor_query = id(new PhabricatorAuthFactorConfigQuery())
      ->setViewer($viewer)
      ->withFactorProviderPHIDs(array_keys($providers));

    if ($users) {
      $factor_query->withUserPHIDs(mpull($users, 'getPHID'));
    }

    $factors = $factor_query->execute();

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
      $provider = $factor->getFactorProvider();

      echo tsprintf(
        "    %s\t%s\t%s\n",
        $handles[$factor->getUserPHID()]->getName(),
        $provider->getProviderFactorKey(),
        $provider->getDisplayName());
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

    $engine = new PhabricatorDestructionEngine();
    foreach ($factors as $factor) {
      $engine->destroyObject($factor);
    }

    $console->writeOut("%s\n", pht('Done.'));

    return 0;
  }

}
