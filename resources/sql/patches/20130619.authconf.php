<?php

$config_map = array(
  'PhabricatorLDAPAuthProvider'           => array(
    'enabled' => 'ldap.auth-enabled',
    'registration' => true,
    'type' => 'ldap',
    'domain' => 'self',
  ),
  'PhabricatorAuthProviderOAuthDisqus'    => array(
    'enabled' => 'disqus.auth-enabled',
    'registration' => 'disqus.registration-enabled',
    'permanent' => 'disqus.auth-permanent',
    'oauth.id' => 'disqus.application-id',
    'oauth.secret' => 'disqus.application-secret',
    'type' => 'disqus',
    'domain' => 'disqus.com',
  ),
  'PhabricatorFacebookAuthProvider'  => array(
    'enabled' => 'facebook.auth-enabled',
    'registration' => 'facebook.registration-enabled',
    'permanent' => 'facebook.auth-permanent',
    'oauth.id' => 'facebook.application-id',
    'oauth.secret' => 'facebook.application-secret',
    'type' => 'facebook',
    'domain' => 'facebook.com',
  ),
  'PhabricatorAuthProviderOAuthGitHub'    => array(
    'enabled' => 'github.auth-enabled',
    'registration' => 'github.registration-enabled',
    'permanent' => 'github.auth-permanent',
    'oauth.id' => 'github.application-id',
    'oauth.secret' => 'github.application-secret',
    'type' => 'github',
    'domain' => 'github.com',
  ),
  'PhabricatorAuthProviderOAuthGoogle'    => array(
    'enabled' => 'google.auth-enabled',
    'registration' => 'google.registration-enabled',
    'permanent' => 'google.auth-permanent',
    'oauth.id' => 'google.application-id',
    'oauth.secret' => 'google.application-secret',
    'type' => 'google',
    'domain' => 'google.com',
  ),
  'PhabricatorPasswordAuthProvider'       => array(
    'enabled' => 'auth.password-auth-enabled',
    'enabled-default' => false,
    'registration' => false,
    'type' => 'password',
    'domain' => 'self',
  ),
);

foreach ($config_map as $provider_class => $spec) {
  $enabled_key = idx($spec, 'enabled');
  $enabled_default = idx($spec, 'enabled-default', false);
  $enabled = PhabricatorEnv::getEnvConfigIfExists(
    $enabled_key,
    $enabled_default);

  if (!$enabled) {
    echo pht('Skipping %s (not enabled).', $provider_class)."\n";
    // This provider was not previously enabled, so we can skip migrating it.
    continue;
  } else {
    echo pht('Migrating %s...', $provider_class)."\n";
  }

  $registration_key = idx($spec, 'registration');
  if ($registration_key === true) {
    $registration = 1;
  } else if ($registration_key === false) {
    $registration = 0;
  } else {
    $registration = (int)PhabricatorEnv::getEnvConfigIfExists(
      $registration_key,
      true);
  }

  $unlink_key = idx($spec, 'permanent');
  if (!$unlink_key) {
    $unlink = 1;
  } else {
    $unlink = (int)(!PhabricatorEnv::getEnvConfigIfExists($unlink_key));
  }

  $config = id(new PhabricatorAuthProviderConfig())
    ->setIsEnabled(1)
    ->setShouldAllowLogin(1)
    ->setShouldAllowRegistration($registration)
    ->setShouldAllowLink(1)
    ->setShouldAllowUnlink($unlink)
    ->setProviderType(idx($spec, 'type'))
    ->setProviderDomain(idx($spec, 'domain'))
    ->setProviderClass($provider_class);

  if (isset($spec['oauth.id'])) {
    $config->setProperty(
      PhabricatorAuthProviderOAuth::PROPERTY_APP_ID,
      PhabricatorEnv::getEnvConfigIfExists(idx($spec, 'oauth.id')));
    $config->setProperty(
      PhabricatorAuthProviderOAuth::PROPERTY_APP_SECRET,
      PhabricatorEnv::getEnvConfigIfExists(idx($spec, 'oauth.secret')));
  }

  switch ($provider_class) {
    case 'PhabricatorFacebookAuthProvider':
      $config->setProperty(
        PhabricatorFacebookAuthProvider::KEY_REQUIRE_SECURE,
        (int)PhabricatorEnv::getEnvConfigIfExists(
          'facebook.require-https-auth'));
      break;
    case 'PhabricatorLDAPAuthProvider':

      $ldap_map = array(
        PhabricatorLDAPAuthProvider::KEY_HOSTNAME
          => 'ldap.hostname',
        PhabricatorLDAPAuthProvider::KEY_PORT
          => 'ldap.port',
        PhabricatorLDAPAuthProvider::KEY_DISTINGUISHED_NAME
          => 'ldap.base_dn',
        PhabricatorLDAPAuthProvider::KEY_SEARCH_ATTRIBUTES
          => 'ldap.search_attribute',
        PhabricatorLDAPAuthProvider::KEY_USERNAME_ATTRIBUTE
          => 'ldap.username-attribute',
        PhabricatorLDAPAuthProvider::KEY_REALNAME_ATTRIBUTES
          => 'ldap.real_name_attributes',
        PhabricatorLDAPAuthProvider::KEY_VERSION
          => 'ldap.version',
        PhabricatorLDAPAuthProvider::KEY_REFERRALS
          => 'ldap.referrals',
        PhabricatorLDAPAuthProvider::KEY_START_TLS
          => 'ldap.start-tls',
        PhabricatorLDAPAuthProvider::KEY_ANONYMOUS_USERNAME
          => 'ldap.anonymous-user-name',
        PhabricatorLDAPAuthProvider::KEY_ANONYMOUS_PASSWORD
          => 'ldap.anonymous-user-password',
        // Update the old "search first" setting to the newer but similar
        // "always search" setting.
        PhabricatorLDAPAuthProvider::KEY_ALWAYS_SEARCH
          => 'ldap.search-first',
        PhabricatorLDAPAuthProvider::KEY_ACTIVEDIRECTORY_DOMAIN
          => 'ldap.activedirectory_domain',
      );

      $defaults = array(
        'ldap.version' => 3,
        'ldap.port' => 389,
      );

      foreach ($ldap_map as $pkey => $ckey) {
        $default = idx($defaults, $ckey);
        $config->setProperty(
          $pkey,
          PhabricatorEnv::getEnvConfigIfExists($ckey, $default));
      }
      break;
  }

  $config->save();
}

echo pht('Done.')."\n";
