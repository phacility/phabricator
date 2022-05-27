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

    $stack = PhabricatorEnv::getConfigSourceStack();
    $stack = $stack->getStack();

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
          'The configuration option "%s" is not recognized. It may be '.
          'misspelled, or it might have existed in an older version of '.
          'the software. It has no effect, and should be corrected or deleted.',
          $key);
        $short = pht('Unknown Config');
        $name = pht('Unknown Configuration Option "%s"', $key);
      }

      $issue = $this->newIssue('config.unknown.'.$key)
        ->setShortName($short)
        ->setName($name)
        ->setSummary($summary);

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
        $command = csprintf('$ ./bin/config delete %s', $key);
        $issue->addCommand($command);
      }

      if ($found_database) {
        $issue->addPhabricatorConfig($key);
      }
    }

    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    foreach ($defined_keys as $key => $value) {
      $option = idx($options, $key);
      if (!$option) {
        continue;
      }

      if (!$option->getLocked()) {
        continue;
      }

      $found_database = false;
      foreach ($stack as $source_key => $source) {
        $value = $source->getKeys(array($key));
        if ($value) {
          if ($source instanceof PhabricatorConfigDatabaseSource) {
            $found_database = true;
            break;
          }
        }
      }

      if (!$found_database) {
        continue;
      }

      // NOTE: These are values which we don't let you edit directly, but edit
      // via other UI workflows. For now, don't raise this warning about them.
      // In the future, before we stop reading database configuration for
      // locked values, we either need to add a flag which lets these values
      // continue reading from the database or move them to some other storage
      // mechanism.
      $soft_locks = array(
        'phabricator.uninstalled-applications',
        'phabricator.application-settings',
        'config.ignore-issues',
        'auth.lock-config',
      );
      $soft_locks = array_fuse($soft_locks);
      if (isset($soft_locks[$key])) {
        continue;
      }

      $doc_name = 'Configuration Guide: Locked and Hidden Configuration';
      $doc_href = PhabricatorEnv::getDoclink($doc_name);

      $set_command = phutil_tag(
        'tt',
        array(),
        csprintf(
          'bin/config set %R <value>',
          $key));

      $summary = pht(
        'Configuration value "%s" is locked, but has a value in the database.',
        $key);
      $message = pht(
        'The configuration value "%s" is locked (so it can not be edited '.
        'from the web UI), but has a database value. Usually, this means '.
        'that it was previously not locked, you set it using the web UI, '.
        'and it later became locked.'.
        "\n\n".
        'You should copy this configuration value to a local configuration '.
        'source (usually by using %s) and then remove it from the database '.
        'with the command below.'.
        "\n\n".
        'For more information on locked and hidden configuration, including '.
        'details about this setup issue, see %s.'.
        "\n\n".
        'This database value is currently respected, but a future version '.
        'of the software will stop respecting database values for locked '.
        'configuration options.',
        $key,
        $set_command,
        phutil_tag(
          'a',
          array(
            'href' => $doc_href,
            'target' => '_blank',
          ),
          $doc_name));
      $command = csprintf(
        '$ ./bin/config delete --database %R',
        $key);

      $this->newIssue('config.locked.'.$key)
        ->setShortName(pht('Deprecated Config Source'))
        ->setName(
          pht(
            'Locked Configuration Option "%s" Has Database Value',
            $key))
        ->setSummary($summary)
        ->setMessage($message)
        ->addCommand($command)
        ->addPhabricatorConfig($key);
    }

    if (PhabricatorEnv::getEnvConfig('feed.http-hooks')) {
      $this->newIssue('config.deprecated.feed.http-hooks')
        ->setShortName(pht('Feed Hooks Deprecated'))
        ->setName(pht('Migrate From "feed.http-hooks" to Webhooks'))
        ->addPhabricatorConfig('feed.http-hooks')
        ->setMessage(
          pht(
            'The "feed.http-hooks" option is deprecated in favor of '.
            'Webhooks. This option will be removed in a future version '.
            'of the software.'.
            "\n\n".
            'You can configure Webhooks in Herald.'.
            "\n\n".
            'To resolve this issue, remove all URIs from "feed.http-hooks".'));
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
      'Global customization of monospaced fonts is no longer supported.');

    $public_mail_reason = pht(
      'Inbound mail addresses are now configured for each application '.
      'in the Applications tool.');

    $gc_reason = pht(
      'Garbage collectors are now configured with "%s".',
      'bin/garbage set-policy');

    $aphlict_reason = pht(
      'Configuration of the notification server has changed substantially. '.
      'For discussion, see T10794.');

    $stale_reason = pht(
      'The Differential revision list view age UI elements have been removed '.
      'to simplify the interface.');

    $global_settings_reason = pht(
      'The "Re: Prefix" and "Vary Subjects" settings are now configured '.
      'in global settings.');

    $dashboard_reason = pht(
      'This option has been removed, you can use Dashboards to provide '.
      'homepage customization. See T11533 for more details.');

    $elastic_reason = pht(
      'Elasticsearch is now configured with "%s".',
      'cluster.search');

    $mailers_reason = pht(
      'Inbound and outbound mail is now configured with "cluster.mailers".');

    $prefix_reason = pht(
      'Per-application mail subject prefix customization is no longer '.
      'directly supported. Prefixes and other strings may be customized with '.
      '"translation.override".');

    $phd_reason = pht(
      'Use "bin/phd debug ..." to get a detailed daemon execution log.');

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
        'Global access controls now exist, see `%s`.',
        'policy.allow-public'),
      'celerity.resource-path' => pht(
        'An alternate resource map is no longer supported. Instead, use '.
        'multiple maps. See T4222.'),
      'metamta.send-immediately' => pht(
        'Mail is now always delivered by the daemons.'),
      'auth.sessions.conduit' => $session_reason,
      'auth.sessions.web' => $session_reason,
      'tokenizer.ondemand' => pht(
        'Typeahead strategies are now managed automatically.'),
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
        'Storage engines are now discovered automatically at runtime.'),
      'storage.upload-size-limit' => pht(
        'Arbitrarily large files are now supported. Consult the '.
        'documentation for configuration details.'),
      'security.allow-outbound-http' => pht(
        'This option has been replaced with the more granular option `%s`.',
        'security.outbound-blacklist'),
      'metamta.reply.show-hints' => pht(
        'Reply hints are no longer shown in mail.'),

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
        'Transaction mail is now always sent with "Precedence: bulk" to '.
        'improve deliverability.'),

      'style.monospace' => $monospace_reason,
      'style.monospace.windows' => $monospace_reason,

      'search.engine-selector' => pht(
        'Available search engines are now automatically discovered at '.
        'runtime.'),

      'metamta.files.public-create-email' => $public_mail_reason,
      'metamta.maniphest.public-create-email' => $public_mail_reason,
      'metamta.maniphest.default-public-author' => $public_mail_reason,
      'metamta.paste.public-create-email' => $public_mail_reason,

      'security.allow-conduit-act-as-user' => pht(
        'Impersonating users over the API is no longer supported.'),

      'feed.public' => pht('The framable public feed is no longer supported.'),

      'auth.login-message' => pht(
        'This configuration option has been replaced with a modular '.
        'handler. See T9346.'),

      'gcdaemon.ttl.herald-transcripts' => $gc_reason,
      'gcdaemon.ttl.daemon-logs' => $gc_reason,
      'gcdaemon.ttl.differential-parse-cache' => $gc_reason,
      'gcdaemon.ttl.markup-cache' => $gc_reason,
      'gcdaemon.ttl.task-archive' => $gc_reason,
      'gcdaemon.ttl.general-cache' => $gc_reason,
      'gcdaemon.ttl.conduit-logs' => $gc_reason,

      'phd.variant-config' => pht(
        'This configuration is no longer relevant because daemons '.
        'restart automatically on configuration changes.'),

      'notification.ssl-cert' => $aphlict_reason,
      'notification.ssl-key' => $aphlict_reason,
      'notification.pidfile' => $aphlict_reason,
      'notification.log' => $aphlict_reason,
      'notification.enabled' => $aphlict_reason,
      'notification.client-uri' => $aphlict_reason,
      'notification.server-uri' => $aphlict_reason,

      'metamta.differential.unified-comment-context' => pht(
        'Inline comments are now always rendered with a limited amount '.
        'of context.'),

      'differential.days-fresh' => $stale_reason,
      'differential.days-stale' => $stale_reason,

      'metamta.re-prefix' => $global_settings_reason,
      'metamta.vary-subjects' => $global_settings_reason,

      'ui.custom-header' => pht(
        'This option has been replaced with `ui.logo`, which provides more '.
        'flexible configuration options.'),

      'welcome.html' => $dashboard_reason,
      'maniphest.priorities.unbreak-now' => $dashboard_reason,
      'maniphest.priorities.needs-triage' => $dashboard_reason,

      'mysql.implementation' => pht(
        'The best available MYSQL implementation is now selected '.
        'automatically.'),

      'mysql.configuration-provider' => pht(
        'Partitioning and replication are now managed in primary '.
        'configuration.'),

      'search.elastic.host' => $elastic_reason,
      'search.elastic.namespace' => $elastic_reason,

      'metamta.mail-adapter' => $mailers_reason,
      'amazon-ses.access-key' => $mailers_reason,
      'amazon-ses.secret-key' => $mailers_reason,
      'amazon-ses.endpoint' => $mailers_reason,
      'mailgun.domain' => $mailers_reason,
      'mailgun.api-key' => $mailers_reason,
      'phpmailer.mailer' => $mailers_reason,
      'phpmailer.smtp-host' => $mailers_reason,
      'phpmailer.smtp-port' => $mailers_reason,
      'phpmailer.smtp-protocol' => $mailers_reason,
      'phpmailer.smtp-user' => $mailers_reason,
      'phpmailer.smtp-password' => $mailers_reason,
      'phpmailer.smtp-encoding' => $mailers_reason,
      'sendgrid.api-user' => $mailers_reason,
      'sendgrid.api-key' => $mailers_reason,

      'celerity.resource-hash' => pht(
        'This option generally did not prove useful. Resource hash keys '.
        'are now managed automatically.'),
      'celerity.enable-deflate' => pht(
        'Resource deflation is now managed automatically.'),
      'celerity.minify' => pht(
        'Resource minification is now managed automatically.'),

      'metamta.domain' => pht(
        'Mail thread IDs are now generated automatically.'),
      'metamta.placeholder-to-recipient' => pht(
        'Placeholder recipients are now generated automatically.'),

      'metamta.mail-key' => pht(
        'Mail object address hash keys are now generated automatically.'),

      'phabricator.csrf-key' => pht(
        'CSRF HMAC keys are now managed automatically.'),

      'metamta.insecure-auth-with-reply-to' => pht(
        'Authenticating users based on "Reply-To" is no longer supported.'),

      'phabricator.allow-email-users' => pht(
        'Public email is now accepted if the associated address has a '.
        'default author, and rejected otherwise.'),

      'metamta.conpherence.subject-prefix' => $prefix_reason,
      'metamta.differential.subject-prefix' => $prefix_reason,
      'metamta.diffusion.subject-prefix' => $prefix_reason,
      'metamta.files.subject-prefix' => $prefix_reason,
      'metamta.legalpad.subject-prefix' => $prefix_reason,
      'metamta.macro.subject-prefix' => $prefix_reason,
      'metamta.maniphest.subject-prefix' => $prefix_reason,
      'metamta.package.subject-prefix' => $prefix_reason,
      'metamta.paste.subject-prefix' => $prefix_reason,
      'metamta.pholio.subject-prefix' => $prefix_reason,
      'metamta.phriction.subject-prefix' => $prefix_reason,

      'aphront.default-application-configuration-class' => pht(
        'This ancient extension point has been replaced with other '.
        'mechanisms, including "AphrontSite".'),

      'differential.whitespace-matters' => pht(
        'Whitespace rendering is now handled automatically.'),

      'phd.pid-directory' => pht(
        'Daemons no longer use PID files.'),

      'phd.trace' => $phd_reason,
      'phd.verbose' => $phd_reason,
    );

    return $ancient_config;
  }

}
