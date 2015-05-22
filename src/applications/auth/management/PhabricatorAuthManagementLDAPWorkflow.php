<?php

final class PhabricatorAuthManagementLDAPWorkflow
  extends PhabricatorAuthManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('ldap')
      ->setExamples('**ldap**')
      ->setSynopsis(
        pht('Analyze and diagnose issues with LDAP configuration.'));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $console->getServer()->setEnableLog(true);

    PhabricatorLDAPAuthProvider::assertLDAPExtensionInstalled();

    $provider = PhabricatorLDAPAuthProvider::getLDAPProvider();
    if (!$provider) {
      $console->writeOut(
        "%s\n",
        pht('The LDAP authentication provider is not enabled.'));
      exit(1);
    }

    if (!function_exists('ldap_connect')) {
      $console->writeOut(
        "%s\n",
        pht('The LDAP extension is not enabled.'));
      exit(1);
    }

    $adapter = $provider->getAdapter();

    $console->writeOut("%s\n", pht('Enter LDAP Credentials'));
    $username = phutil_console_prompt(pht('LDAP Username: '));
    if (!strlen($username)) {
      throw new PhutilArgumentUsageException(
        pht('You must enter an LDAP username.'));
    }

    phutil_passthru('stty -echo');
    $password = phutil_console_prompt(pht('LDAP Password: '));
    phutil_passthru('stty echo');

    if (!strlen($password)) {
      throw new PhutilArgumentUsageException(
        pht('You must enter an LDAP password.'));
    }

    $adapter->setLoginUsername($username);
    $adapter->setLoginPassword(new PhutilOpaqueEnvelope($password));

    $console->writeOut("\n");
    $console->writeOut("%s\n", pht('Connecting to LDAP...'));

    $account_id = $adapter->getAccountID();
    if ($account_id) {
      $console->writeOut("%s\n", pht('Found LDAP Account: %s', $account_id));
    } else {
      $console->writeOut("%s\n", pht('Unable to find LDAP account!'));
    }

    return 0;
  }

}
