<?php

final class PhabricatorMySQLConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('MySQL');
  }

  public function getDescription() {
    return pht('Database configuration.');
  }

  public function getFontIcon() {
    return 'fa-database';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    return array(
      $this->newOption('mysql.host', 'string', 'localhost')
        ->setLocked(true)
        ->setDescription(
          pht('MySQL database hostname.'))
        ->addExample('localhost', pht('MySQL on this machine'))
        ->addExample('db.example.com:3300', pht('Nonstandard port')),
      $this->newOption('mysql.user', 'string', 'root')
        ->setLocked(true)
        ->setDescription(
          pht('MySQL username to use when connecting to the database.')),
      $this->newOption('mysql.pass', 'string', null)
        ->setHidden(true)
        ->setDescription(
          pht('MySQL password to use when connecting to the database.')),
      $this->newOption(
        'mysql.configuration-provider',
        'class',
        'DefaultDatabaseConfigurationProvider')
        ->setLocked(true)
        ->setBaseClass('DatabaseConfigurationProvider')
        ->setSummary(
          pht('Configure database configuration class.'))
        ->setDescription(
          pht(
            'Phabricator chooses which database to connect to through a '.
            'swappable configuration provider. You almost certainly do not '.
            'need to change this.')),
      $this->newOption(
        'mysql.implementation',
        'class',
        (extension_loaded('mysqli')
          ? 'AphrontMySQLiDatabaseConnection'
          : 'AphrontMySQLDatabaseConnection'))
        ->setLocked(true)
        ->setBaseClass('AphrontMySQLDatabaseConnectionBase')
        ->setSummary(
          pht('Configure database connection class.'))
        ->setDescription(
          pht(
            'Phabricator connects to MySQL through a swappable abstraction '.
            'layer. You can choose an alternate implementation by setting '.
            'this option. To provide your own implementation, extend '.
            '`%s`. It is very unlikely that you need to change this.',
            'AphrontMySQLDatabaseConnectionBase')),
      $this->newOption('storage.default-namespace', 'string', 'phabricator')
        ->setLocked(true)
        ->setSummary(
          pht('The namespace that Phabricator databases should use.'))
        ->setDescription(
          pht(
            "Phabricator puts databases in a namespace, which defaults to ".
            "'phabricator' -- for instance, the Differential database is ".
            "named 'phabricator_differential' by default. You can change ".
            "this namespace if you want. Normally, you should not do this ".
            "unless you are developing Phabricator and using namespaces to ".
            "separate multiple sandbox datasets.")),
        $this->newOption('mysql.port', 'string', null)
        ->setLocked(true)
        ->setDescription(
          pht('MySQL port to use when connecting to the database.')),
    );
  }

}
