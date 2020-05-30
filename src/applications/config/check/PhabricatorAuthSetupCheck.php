<?php

final class PhabricatorAuthSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_IMPORTANT;
  }

  protected function executeChecks() {
    // NOTE: We're not actually building these providers. Building providers
    // can require additional configuration to be present (e.g., to build
    // redirect and login URIs using `phabricator.base-uri`) and it won't
    // necessarily be available when running setup checks.

    // Since this check is only meant as a hint to new administrators about
    // steps they should take, we don't need to be thorough about checking
    // that providers are enabled, available, correctly configured, etc. As
    // long as they've created some kind of provider in the auth app before,
    // they know that it exists and don't need the hint to go check it out.

    $configs = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->execute();

    $did_warn = false;
    if (!$configs) {
      $message = pht(
        'You have not configured any authentication providers yet. You '.
        'should add a provider (like username/password, LDAP, or GitHub '.
        'OAuth) so users can register and log in. You can add and configure '.
        'providers using the Auth Application.');

      $this
        ->newIssue('auth.noproviders')
        ->setShortName(pht('No Auth Providers'))
        ->setName(pht('No Authentication Providers Configured'))
        ->setMessage($message)
        ->addLink('/auth/', pht('Auth Application'));

      $did_warn = true;
    }

    // This check is meant for new administrators, but we don't want to
    // show both this warning and the "No Auth Providers" warning.  Also,
    // show this as a reminder to go back and do a `bin/auth lock` after
    // they make their desired changes.
    $is_locked = PhabricatorEnv::getEnvConfig('auth.lock-config');
    if (!$is_locked && !$did_warn) {
      $message = pht(
        'Your authentication provider configuration is unlocked. Once you '.
        'finish setting up or modifying authentication, you should lock the '.
        'configuration to prevent unauthorized changes.'.
        "\n\n".
        'Leaving your authentication provider configuration unlocked '.
        'increases the damage that a compromised administrator account can '.
        'do to your install. For example, an attacker who compromises an '.
        'administrator account can change authentication providers to point '.
        'at a server they control and attempt to intercept usernames and '.
        'passwords.'.
        "\n\n".
        'To prevent this attack, you should configure authentication, and '.
        'then lock the configuration by running "bin/auth lock" from the '.
        'command line. This will prevent changing the authentication config '.
        'without first running "bin/auth unlock".');
      $this
        ->newIssue('auth.config-unlocked')
        ->setShortName(pht('Auth Config Unlocked'))
        ->setName(pht('Authenticaton Configuration Unlocked'))
        ->setSummary(
          pht(
            'Authentication configuration is currently unlocked. Once you '.
            'finish configuring authentication, you should lock it.'))
        ->setMessage($message)
        ->addRelatedPhabricatorConfig('auth.lock-config')
        ->addCommand(
          hsprintf(
            '<tt>phabricator/ $</tt> ./bin/auth lock'));
    }
  }
}
