<?php

final class PhabricatorPHPPreflightSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_PHP;
  }

  public function isPreflightCheck() {
    return true;
  }

  protected function executeChecks() {
    $version = phpversion();
    if (version_compare($version, 7, '>=') &&
        version_compare($version, 7.1, '<')) {
      $message = pht(
        'You are running PHP version %s. PHP versions between 7.0 and 7.1 '.
        'are not supported'.
        "\n\n".
        'PHP removed reqiured signal handling features in '.
        'PHP 7.0, and did not restore an equivalent mechanism until PHP 7.1.'.
        "\n\n".
        'Upgrade to PHP 7.1 or newer (recommended) or downgrade to an older '.
        'version of PHP 5 (discouraged).',
        $version);

      $this->newIssue('php.version7')
        ->setIsFatal(true)
        ->setName(pht('PHP 7.0-7.1 Not Supported'))
        ->setMessage($message)
        ->addLink(
          'https://phurl.io/u/php7',
          pht('PHP 7 Compatibility Information'));

      return;
    }

    // TODO: This can be removed entirely because the minimum PHP version is
    // now PHP 5.5, which does not have safe mode.

    $safe_mode = ini_get('safe_mode');
    if ($safe_mode) {
      $message = pht(
        "You have '%s' enabled in your PHP configuration, but this software ".
        "will not run in safe mode. Safe mode has been deprecated in PHP 5.3 ".
        "and removed in PHP 5.4.\n\nDisable safe mode to continue.",
        'safe_mode');

      $this->newIssue('php.safe_mode')
        ->setIsFatal(true)
        ->setName(pht('Disable PHP %s', 'safe_mode'))
        ->setMessage($message)
        ->addPHPConfig('safe_mode');
      return;
    }

    // Check for `disable_functions` or `disable_classes`. Although it's
    // possible to disable a bunch of functions (say, `array_change_key_case()`)
    // and classes and still have Phabricator work fine, it's unreasonably
    // difficult for us to be sure we'll even survive setup if these options
    // are enabled. Phabricator needs access to the most dangerous functions,
    // so there is no reasonable configuration value here which actually
    // provides a benefit while guaranteeing Phabricator will run properly.

    $disable_options = array('disable_functions', 'disable_classes');
    foreach ($disable_options as $disable_option) {
      $disable_value = ini_get($disable_option);
      if ($disable_value) {

        // By default Debian installs the pcntl extension but disables all of
        // its functions using configuration. Whitelist disabling these
        // functions so that Debian PHP works out of the box (we do not need to
        // call these functions from the web UI). This is pretty ridiculous but
        // it's not the users' fault and they haven't done anything crazy to
        // get here, so don't make them pay for Debian's unusual choices.
        // See: http://bugs.debian.org/cgi-bin/bugreport.cgi?bug=605571
        $fatal = true;
        if ($disable_option == 'disable_functions') {
          $functions = preg_split('/[, ]+/', $disable_value);
          $functions = array_filter($functions);
          foreach ($functions as $k => $function) {
            if (preg_match('/^pcntl_/', $function)) {
              unset($functions[$k]);
            }
          }
          if (!$functions) {
            $fatal = false;
          }
        }

        if ($fatal) {
          $message = pht(
            "You have '%s' enabled in your PHP configuration.\n\n".
            "This option is not compatible with this software. Remove ".
            "'%s' from your configuration to continue.",
            $disable_option,
            $disable_option);

          $this->newIssue('php.'.$disable_option)
            ->setIsFatal(true)
            ->setName(pht('Remove PHP %s', $disable_option))
            ->setMessage($message)
            ->addPHPConfig($disable_option);
        }
      }
    }

    $overload_option = 'mbstring.func_overload';
    $func_overload = ini_get($overload_option);
    if ($func_overload) {
      $message = pht(
        "You have '%s' enabled in your PHP configuration.\n\n".
        "This option is not compatible with this software. Disable ".
        "'%s' in your PHP configuration to continue.",
        $overload_option,
        $overload_option);

      $this->newIssue('php'.$overload_option)
        ->setIsFatal(true)
        ->setName(pht('Disable PHP %s', $overload_option))
        ->setMessage($message)
        ->addPHPConfig($overload_option);
    }

    $open_basedir = ini_get('open_basedir');
    if ($open_basedir !== null && strlen($open_basedir)) {
      // If `open_basedir` is set, just fatal. It's technically possible for
      // us to run with certain values of `open_basedir`, but: we can only
      // raise fatal errors from preflight steps, so we'd have to do this check
      // in two parts to support fatal and advisory versions; it's much simpler
      // to just fatal instead of trying to test all the different things we
      // may need to access in the filesystem; and use of this option seems
      // rare (particularly in supported environments).

      $message = pht(
        "Your server is configured with '%s', which prevents this software ".
        "from opening files it requires access to.\n\n".
        "Disable this setting to continue.",
        'open_basedir');

      $issue = $this->newIssue('php.open_basedir')
        ->setName(pht('Disable PHP %s', 'open_basedir'))
        ->addPHPConfig('open_basedir')
        ->setIsFatal(true)
        ->setMessage($message);
    }

  }
}
