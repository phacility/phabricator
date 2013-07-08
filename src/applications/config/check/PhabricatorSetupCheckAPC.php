<?php

final class PhabricatorSetupCheckAPC extends PhabricatorSetupCheck {

  protected function executeChecks() {
    if (!extension_loaded('apc')) {
      $message = pht(
        "Installing the PHP extension 'APC' (Alternative PHP Cache) will ".
        "dramatically improve performance.");

      $this
        ->newIssue('extension.apc')
        ->setShortName(pht('APC'))
        ->setName(pht("PHP Extension 'APC' Not Installed"))
        ->setMessage($message)
        ->addPHPExtension('apc');
      return;
    }

    if (!ini_get('apc.enabled')) {
      $summary = pht("Enabling APC will dramatically improve performance.");
      $message = pht(
        "The PHP extension 'APC' is installed, but not enabled in your PHP ".
        "configuration. Enabling it will dramatically improve Phabricator ".
        "performance. Edit the 'apc.enabled' setting to enable the extension.");

      $this
        ->newIssue('extension.apc.enabled')
        ->setShortName(pht('APC Disabled'))
        ->setName(pht("PHP Extension 'APC' Not Enabled"))
        ->setSummary($summary)
        ->setMessage($message)
        ->addPHPConfig('apc.enabled');
      return;
    }

    $is_dev = PhabricatorEnv::getEnvConfig('phabricator.developer-mode');
    $is_stat_enabled = ini_get('apc.stat');

    $issue_key = null;
    if ($is_stat_enabled && !$is_dev) {
      $issue_key = 'extension.apc.stat-enabled';
      $short = pht("'apc.stat' Enabled");
      $long = pht("'apc.stat' Enabled in Production");
      $summary = pht(
        "'apc.stat' is currently enabled, but should probably be disabled.");
      $message = pht(
        "'apc.stat' is currently enabled in your PHP configuration. For most ".
        "Phabricator installs, 'apc.stat' should be disabled. This will ".
        "slightly improve performance (PHP will do fewer disk reads) and make ".
        "updates safer (PHP won't read in the middle of a 'git pull').\n\n".
        "(If you are developing for Phabricator, leave 'apc.stat' enabled but ".
        "enable 'phabricator.developer-mode'.)");
    } else if (!$is_stat_enabled && $is_dev) {
      $issue_key = 'extension.apc.stat-disabled';
      $short = pht("'apc.stat' Disabled");
      $long = pht("'apc.stat' Disabled in Development");
      $summary = pht(
        "'apc.stat' is currently disabled, but should probably be enabled ".
        "in development mode.");
      $message = pht(
        "'apc.stat' is disabled in your PHP configuration, but Phabricator is ".
        "set to developer mode. Normally, you should enable 'apc.stat' for ".
        "development installs so you don't need to restart your webserver ".
        "after making changes to the code.\n\n".
        "You can enable 'apc.stat', or disable 'phabricator.developer-mode', ".
        "or safely ignore this warning if you have some reasonining behind ".
        "your current configuration.");
    }

    if ($issue_key !== null) {
      $this
        ->newIssue($issue_key)
        ->setShortName($short)
        ->setName($long)
        ->setSummary($summary)
        ->setMessage($message)
        ->addPHPConfig('apc.stat')
        ->addPhabricatorConfig('phabricator.developer-mode');
    }
  }

}
