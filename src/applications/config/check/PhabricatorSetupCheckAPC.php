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
    }

  }
}
