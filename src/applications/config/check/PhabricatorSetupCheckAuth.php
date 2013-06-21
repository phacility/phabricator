<?php

final class PhabricatorSetupCheckAuth extends PhabricatorSetupCheck {

  protected function executeChecks() {
    $providers = PhabricatorAuthProvider::getAllEnabledProviders();
    if (!$providers) {
      $message = pht(
        'You have not configured any authentication providers yet. You '.
        'should add a provider (like username/password, LDAP, or GitHub '.
        'OAuth) so users can register and log in. You can add and configure '.
        'providers %s.',
        phutil_tag(
          'a',
          array(
            'href' => '/auth/',
          ),
          pht('using the "Auth" application')));

      $this
        ->newIssue('auth.noproviders')
        ->setShortName(pht('No Auth Providers'))
        ->setName(pht('No Authentication Providers Configured'))
        ->setMessage($message);
    }
  }
}
