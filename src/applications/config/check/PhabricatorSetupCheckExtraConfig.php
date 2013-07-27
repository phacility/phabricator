<?php

final class PhabricatorSetupCheckExtraConfig extends PhabricatorSetupCheck {

  protected function executeChecks() {
    $ancient_config = self::getAncientConfig();

    $all_keys = PhabricatorEnv::getAllConfigKeys();
    $all_keys = array_keys($all_keys);
    sort($all_keys);

    $defined_keys = PhabricatorApplicationConfigOptions::loadAllOptions();

    foreach ($all_keys as $key) {
      if (isset($defined_keys[$key])) {
        continue;
      }

      if (isset($ancient_config[$key])) {
        $summary = pht(
          'This option has been removed. You may delete it at your '.
          'convenience.');
        $message = pht(
          "The configuration option '%s' has been removed. You may delete ".
          "it at your convenience.".
          "\n\n%s",
          $key,
          $ancient_config[$key]);
        $short = pht('Obsolete Config');
        $name = pht('Obsolete Configuration Option "%s"', $key);
      } else {
        $summary = pht("This option is not recognized. It may be misspelled.");
        $message = pht(
          "The configuration option '%s' is not recognized. It may be ".
          "misspelled, or it might have existed in an older version of ".
          "Phabricator. It has no effect, and should be corrected or deleted.",
          $key);
        $short = pht('Unknown Config');
        $name = pht('Unknown Configuration Option "%s"', $key);
      }

      $issue = $this->newIssue('config.unknown.'.$key)
        ->setShortName($short)
        ->setName($name)
        ->setSummary($summary);

      $stack = PhabricatorEnv::getConfigSourceStack();
      $stack = $stack->getStack();

      $found = array();
      $found_local = false;
      $found_database = false;

      foreach ($stack as $source_key => $source) {
        $value = $source->getKeys(array($key));
        if ($value) {
          $found[] = $source->getName();
          if ($source instanceof PhabricatorConfigDatabaseSource) {
            $found_database = true;
          }
          if ($source instanceof PhabricatorConfigLocalSource) {
            $found_local = true;
          }
        }
      }

      $message = $message."\n\n".pht(
        "This configuration value is defined in these %d ".
        "configuration source(s): %s.",
        count($found),
        implode(', ', $found));
      $issue->setMessage($message);

      if ($found_local) {
        $command = csprintf('phabricator/ $ ./bin/config delete %s', $key);
        $issue->addCommand($command);
      }

      if ($found_database) {
        $issue->addPhabricatorConfig($key);
      }
    }
  }

  /**
   * Return a map of deleted config options. Keys are option keys; values are
   * explanations of what happened to the option.
   */
  public static function getAncientConfig() {
    $reason_auth = pht(
      'This option has been migrated to the "Auth" application. Your old '.
      'configuration is still in effect, but now stored in "Auth" instead of '.
      'configuration. Going forward, you can manage authentication from '.
      'the web UI.');

    $auth_config = array(
      'controller.oauth-registration',
      'auth.password-auth-enabled',
      'facebook.auth-enabled',
      'facebook.registration-enabled',
      'facebook.auth-permanent',
      'facebook.application-id',
      'facebook.application-secret',
      'facebook.require-https-auth',
      'github.auth-enabled',
      'github.registration-enabled',
      'github.auth-permanent',
      'github.application-id',
      'github.application-secret',
      'google.auth-enabled',
      'google.registration-enabled',
      'google.auth-permanent',
      'google.application-id',
      'google.application-secret',
      'ldap.auth-enabled',
      'ldap.hostname',
      'ldap.port',
      'ldap.base_dn',
      'ldap.search_attribute',
      'ldap.search-first',
      'ldap.username-attribute',
      'ldap.real_name_attributes',
      'ldap.activedirectory_domain',
      'ldap.version',
      'ldap.referrals',
      'ldap.anonymous-user-name',
      'ldap.anonymous-user-password',
      'ldap.start-tls',
      'disqus.auth-enabled',
      'disqus.registration-enabled',
      'disqus.auth-permanent',
      'disqus.application-id',
      'disqus.application-secret',
      'phabricator.oauth-uri',
      'phabricator.auth-enabled',
      'phabricator.registration-enabled',
      'phabricator.auth-permanent',
      'phabricator.application-id',
      'phabricator.application-secret',
    );

    $ancient_config = array_fill_keys($auth_config, $reason_auth);

    $ancient_config += array(
      'phid.external-loaders' =>
        pht(
          'External loaders have been replaced. Extend `PhabricatorPHIDType` '.
          'to implement new PHID and handle types.'),
    );

    return $ancient_config;
  }
}
