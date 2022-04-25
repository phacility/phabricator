<?php

final class PhabricatorTimezoneSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

  protected function executeChecks() {
    $php_value = ini_get('date.timezone');
    if ($php_value) {
      $old = date_default_timezone_get();
      $ok = @date_default_timezone_set($php_value);
      date_default_timezone_set($old);

      if (!$ok) {
        $message = pht(
          'Your PHP configuration selects an invalid timezone. '.
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
      'Without a configured timezone, PHP will emit warnings when working '.
      'with dates, and dates and times may not display correctly.');

    $message = pht(
      "Your configuration fails to specify a server timezone. You can either ".
      "set the PHP configuration value '%s' or the %s configuration ".
      "value '%s' to specify one.",
      'date.timezone',
      PlatformSymbols::getPlatformServerName(),
      'phabricator.timezone');

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
