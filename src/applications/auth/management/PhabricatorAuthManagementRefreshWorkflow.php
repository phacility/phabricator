<?php

final class PhabricatorAuthManagementRefreshWorkflow
  extends PhabricatorAuthManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('refresh')
      ->setExamples('**refresh**')
      ->setSynopsis(
        pht(
          'Refresh OAuth access tokens. This is primarily useful for '.
          'development and debugging.'))
      ->setArguments(
        array(
          array(
            'name' => 'user',
            'param' => 'user',
            'help' => pht('Refresh tokens for a given user.'),
          ),
          array(
            'name' => 'type',
            'param' => 'provider',
            'help' => pht('Refresh tokens for a given provider type.'),
          ),
          array(
            'name' => 'domain',
            'param' => 'domain',
            'help' => pht('Refresh tokens for a given domain.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $viewer = $this->getViewer();

    $query = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ));

    $username = $args->getArg('user');
    if (strlen($username)) {
      $user = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withUsernames(array($username))
        ->executeOne();
      if ($user) {
        $query->withUserPHIDs(array($user->getPHID()));
      } else {
        throw new PhutilArgumentUsageException(
          pht('No such user "%s"!', $username));
      }
    }


    $type = $args->getArg('type');
    if (strlen($type)) {
      $query->withAccountTypes(array($type));
    }

    $domain = $args->getArg('domain');
    if (strlen($domain)) {
      $query->withAccountDomains(array($domain));
    }

    $accounts = $query->execute();

    if (!$accounts) {
      throw new PhutilArgumentUsageException(
        pht('No accounts match the arguments!'));
    } else {
      $console->writeOut(
        "%s\n",
        pht(
          'Found %s account(s) to refresh.',
          phutil_count($accounts)));
    }

    $providers = PhabricatorAuthProvider::getAllEnabledProviders();

    foreach ($accounts as $account) {
      $console->writeOut(
        "%s\n",
        pht(
          'Refreshing account #%d (%s/%s).',
          $account->getID(),
          $account->getAccountType(),
          $account->getAccountDomain()));

      $key = $account->getProviderKey();
      if (empty($providers[$key])) {
        $console->writeOut(
          "> %s\n",
          pht('Skipping, provider is not enabled or does not exist.'));
        continue;
      }

      $provider = $providers[$key];
      if (!($provider instanceof PhabricatorOAuth2AuthProvider)) {
        $console->writeOut(
          "> %s\n",
          pht('Skipping, provider is not an OAuth2 provider.'));
        continue;
      }

      $adapter = $provider->getAdapter();
      if (!$adapter->supportsTokenRefresh()) {
        $console->writeOut(
          "> %s\n",
          pht('Skipping, provider does not support token refresh.'));
        continue;
      }

      $refresh_token = $account->getProperty('oauth.token.refresh');
      if (!$refresh_token) {
        $console->writeOut(
          "> %s\n",
          pht('Skipping, provider has no stored refresh token.'));
        continue;
      }

      $console->writeOut(
        "+ %s\n",
        pht(
          'Refreshing token, current token expires in %s seconds.',
          new PhutilNumber(
            $account->getProperty('oauth.token.access.expires') - time())));

      $token = $provider->getOAuthAccessToken($account, $force_refresh = true);
      if (!$token) {
        $console->writeOut(
          "* %s\n",
          pht('Unable to refresh token!'));
        continue;
      }

      $console->writeOut(
        "+ %s\n",
        pht(
          'Refreshed token, new token expires in %s seconds.',
          new PhutilNumber(
            $account->getProperty('oauth.token.access.expires') - time())));

    }

    $console->writeOut("%s\n", pht('Done.'));

    return 0;
  }

}
