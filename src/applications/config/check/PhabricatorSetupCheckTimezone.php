<?php

final class PhabricatorSetupCheckTimezone extends PhabricatorSetupCheck {

  protected function executeChecks() {
    $php_value = ini_get('date.timezone');
    if ($php_value) {
      $old = date_default_timezone_get();
      $ok = @date_default_timezone_set($php_value);
      date_default_timezone_set($old);

      if (!$ok) {
        $message = pht(
          'Your PHP configuration configuration selects an invalid timezone. '.
          'Select a valid timezone.');

        $this
          ->newIssue('php.date.timezone')
          ->setShortName(pht('PHP Timezone'))
          ->setName(pht('PHP Timezone Invalid'))
          ->setMessage($message)
          ->addPHPConfig('date.timezone');
      }
    }

    $timezone = nonempty(
      PhabricatorEnv::getEnvConfig('phabricator.timezone'),
      ini_get('date.timezone'));
    if ($timezone) {
      return;
    }

    $summary = pht(
      "Without a configured timezone, PHP will emit warnings when working ".
      "with dates, and dates and times may not display correctly.");

    $message = pht(
      "Your configuration fails to specify a server timezone. You can either ".
      "set the PHP configuration value 'date.timezone' or the Phabricator ".
      "configuration value 'phabricator.timezone' to specify one.");

    $this
      ->newIssue('config.timezone')
      ->setShortName(pht('Timezone'))
      ->setName(pht('Server Timezone Not Configured'))
      ->setSummary($summary)
      ->setMessage($message)
      ->addPHPConfig('date.timezone')
      ->addPhabricatorConfig('phabricator.timezone');
  }
}
