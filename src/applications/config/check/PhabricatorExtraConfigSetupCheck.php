<?php

final class PhabricatorExtraConfigSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

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
        $summary = pht('This option is not recognized. It may be misspelled.');
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
        'This configuration value is defined in these %d '.
        'configuration source(s): %s.',
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

    $markup_reason = pht(
      'Custom remarkup rules are now added by subclassing '.
      '%s or %s.',
      'PhabricatorRemarkupCustomInlineRule',
      'PhabricatorRemarkupCustomBlockRule');

    $session_reason = pht(
      'Sessions now expire and are garbage collected rather than having an '.
      'arbitrary concurrency limit.');

    $differential_field_reason = pht(
      'All Differential fields are now managed through the configuration '.
      'option "%s". Use that option to configure which fields are shown.',
      'differential.fields');

    $reply_domain_reason = pht(
      'Individual application reply handler domains have been removed. '.
      'Configure a reply domain with "%s".',
      'metamta.reply-handler-domain');

    $reply_handler_reason = pht(
      'Reply handlers can no longer be overridden with configuration.');

    $monospace_reason = pht(
      'Phabricator no longer supports global customization of monospaced '.
      'fonts.');

    $public_mail_reason = pht(
      'Inbound mail addresses are now configured for each application '.
      'in the Applications tool.');

    $ancient_config += array(
      'phid.external-loaders' =>
        pht(
          'External loaders have been replaced. Extend `%s` '.
          'to implement new PHID and handle types.',
          'PhabricatorPHIDType'),
      'maniphest.custom-task-extensions-class' =>
        pht(
          'Maniphest fields are now loaded automatically. '.
          'You can configure them with `%s`.',
          'maniphest.fields'),
      'maniphest.custom-fields' =>
        pht(
          'Maniphest fields are now defined in `%s`. '.
          'Existing definitions have been migrated.',
          'maniphest.custom-field-definitions'),
      'differential.custom-remarkup-rules' => $markup_reason,
      'differential.custom-remarkup-block-rules' => $markup_reason,
      'auth.sshkeys.enabled' => pht(
        'SSH keys are now actually useful, so they are always enabled.'),
      'differential.anonymous-access' => pht(
        'Phabricator now has meaningful global access controls. See `%s`.',
        'policy.allow-public'),
      'celerity.resource-path' => pht(
        'An alternate resource map is no longer supported. Instead, use '.
        'multiple maps. See T4222.'),
      'metamta.send-immediately' => pht(
        'Mail is now always delivered by the daemons.'),
      'auth.sessions.conduit' => $session_reason,
      'auth.sessions.web' => $session_reason,
      'tokenizer.ondemand' => pht(
        'Phabricator now manages typeahead strategies automatically.'),
      'differential.revision-custom-detail-renderer' => pht(
        'Obsolete; use standard rendering events instead.'),
      'differential.show-host-field' => $differential_field_reason,
      'differential.show-test-plan-field' => $differential_field_reason,
      'differential.field-selector' => $differential_field_reason,
      'phabricator.show-beta-applications' => pht(
        'This option has been renamed to `%s` to emphasize the '.
        'unfinished nature of many prototype applications. '.
        'Your existing setting has been migrated.',
        'phabricator.show-prototypes'),
      'notification.user' => pht(
        'The notification server no longer requires root permissions. Start '.
        'the server as the user you want it to run under.'),
      'notification.debug' => pht(
        'Notifications no longer have a dedicated debugging mode.'),
      'translation.provider' => pht(
        'The translation implementation has changed and providers are no '.
        'longer used or supported.'),
      'config.mask' => pht(
        'Use `%s` instead of this option.',
        'config.hide'),
      'phd.start-taskmasters' => pht(
        'Taskmasters now use an autoscaling pool. You can configure the '.
        'pool size with `%s`.',
        'phd.taskmasters'),
      'storage.engine-selector' => pht(
        'Phabricator now automatically discovers available storage engines '.
        'at runtime.'),
      'storage.upload-size-limit' => pht(
        'Phabricator now supports arbitrarily large files. Consult the '.
        'documentation for configuration details.'),
      'security.allow-outbound-http' => pht(
        'This option has been replaced with the more granular option `%s`.',
        'security.outbound-blacklist'),
      'metamta.reply.show-hints' => pht(
        'Phabricator no longer shows reply hints in mail.'),

      'metamta.differential.reply-handler-domain' => $reply_domain_reason,
      'metamta.diffusion.reply-handler-domain' => $reply_domain_reason,
      'metamta.macro.reply-handler-domain' => $reply_domain_reason,
      'metamta.maniphest.reply-handler-domain' => $reply_domain_reason,
      'metamta.pholio.reply-handler-domain' => $reply_domain_reason,

      'metamta.diffusion.reply-handler' => $reply_handler_reason,
      'metamta.differential.reply-handler' => $reply_handler_reason,
      'metamta.maniphest.reply-handler' => $reply_handler_reason,
      'metamta.package.reply-handler' => $reply_handler_reason,

      'metamta.precedence-bulk' => pht(
        'Phabricator now always sends transaction mail with '.
        '"Precedence: bulk" to improve deliverability.'),

      'style.monospace' => $monospace_reason,
      'style.monospace.windows' => $monospace_reason,

      'search.engine-selector' => pht(
        'Phabricator now automatically discovers available search engines '.
        'at runtime.'),

      'metamta.files.public-create-email' => $public_mail_reason,
      'metamta.maniphest.public-create-email' => $public_mail_reason,
      'metamta.maniphest.default-public-author' => $public_mail_reason,
      'metamta.paste.public-create-email' => $public_mail_reason,

      'security.allow-conduit-act-as-user' => pht(
        'Impersonating users over the API is no longer supported.'),
    );

    return $ancient_config;
  }
}
