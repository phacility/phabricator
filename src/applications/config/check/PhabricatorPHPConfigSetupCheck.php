<?php

/**
 * Noncritical PHP configuration checks.
 *
 * For critical checks, see @{class:PhabricatorPHPPreflightSetupCheck}.
 */
final class PhabricatorPHPConfigSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_PHP;
  }

  protected function executeChecks() {

    if (empty($_SERVER['REMOTE_ADDR'])) {
      $doc_href = PhabricatorEnv::getDoclink('Configuring a Preamble Script');

      $summary = pht(
        'You likely need to fix your preamble script so '.
        'REMOTE_ADDR is no longer empty.');

      $message = pht(
        'No REMOTE_ADDR is available, so Phabricator cannot determine the '.
        'origin address for requests. This will prevent Phabricator from '.
        'performing important security checks. This most often means you '.
        'have a mistake in your preamble script. Consult the documentation '.
        '(%s) and double-check that the script is written correctly.',
        phutil_tag(
          'a',
          array(
            'href' => $doc_href,
            'target' => '_blank',
            ),
          pht('Configuring a Preamble Script')));

      $this->newIssue('php.remote_addr')
        ->setName(pht('No REMOTE_ADDR available'))
        ->setSummary($summary)
        ->setMessage($message);
    }

    if (version_compare(phpversion(), '7', '>=')) {
      // This option was removed in PHP7.
      $raw_post_data = -1;
    } else {
      $raw_post_data = (int)ini_get('always_populate_raw_post_data');
    }

    if ($raw_post_data != -1) {
      $summary = pht(
        'PHP setting "%s" should be set to "-1" to avoid deprecation '.
        'warnings.',
        'always_populate_raw_post_data');

      $message = pht(
        'The "%s" key is set to some value other than "-1" in your PHP '.
        'configuration. This can cause PHP to raise deprecation warnings '.
        'during process startup. Set this option to "-1" to prevent these '.
        'warnings from appearing.',
        'always_populate_raw_post_data');

      $this->newIssue('php.always_populate_raw_post_data')
        ->setName(pht('Disable PHP %s', 'always_populate_raw_post_data'))
        ->setSummary($summary)
        ->setMessage($message)
        ->addPHPConfig('always_populate_raw_post_data');
    }

    if (!extension_loaded('mysqli')) {
      $summary = pht(
        'Install the MySQLi extension to improve database behavior.');

      $message = pht(
        'PHP is currently using the very old "mysql" extension to interact '.
        'with the database. You should install the newer "mysqli" extension '.
        'to improve behaviors (like error handling and query timeouts).'.
        "\n\n".
        'Phabricator will work with the older extension, but upgrading to the '.
        'newer extension is recommended.'.
        "\n\n".
        'You may be able to install the extension with a command like: %s',

        // NOTE: We're intentionally telling you to install "mysqlnd" here; on
        // Ubuntu, there's no separate "mysqli" package.
        phutil_tag('tt', array(), 'sudo apt-get install php5-mysqlnd'));

      $this->newIssue('php.mysqli')
        ->setName(pht('MySQLi Extension Not Available'))
        ->setSummary($summary)
        ->setMessage($message);
    } else if (!defined('MYSQLI_ASYNC')) {
      $summary = pht(
        'Configure the MySQL Native Driver to improve database behavior.');

      $message = pht(
        'PHP is currently using the older MySQL external driver instead of '.
        'the newer MySQL native driver. The older driver lacks options and '.
        'features (like support for query timeouts) which allow Phabricator '.
        'to interact better with the database.'.
        "\n\n".
        'Phabricator will work with the older driver, but upgrading to the '.
        'native driver is recommended.'.
        "\n\n".
        'You may be able to install the native driver with a command like: %s',
        phutil_tag('tt', array(), 'sudo apt-get install php5-mysqlnd'));


      $this->newIssue('php.myqlnd')
        ->setName(pht('MySQL Native Driver Not Available'))
        ->setSummary($summary)
        ->setMessage($message);
    }

  }

}
