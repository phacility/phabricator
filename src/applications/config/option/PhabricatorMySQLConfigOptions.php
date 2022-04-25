<?php

final class PhabricatorMySQLConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('MySQL');
  }

  public function getDescription() {
    return pht('Database configuration.');
  }

  public function getIcon() {
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
      $this->newOption('storage.default-namespace', 'string', 'phabricator')
        ->setLocked(true)
        ->setSummary(
          pht('The namespace that databases should use.'))
        ->setDescription(
          pht(
            "Databases are created in a namespace, which defaults to ".
            "'phabricator' -- for instance, the Differential database is ".
            "named 'phabricator_differential' by default. You can change ".
            "this namespace if you want. Normally, you should not do this ".
            "unless you are developing extensions and using namespaces to ".
            "separate multiple sandbox datasets.")),
        $this->newOption('mysql.port', 'string', null)
        ->setLocked(true)
        ->setDescription(
          pht('MySQL port to use when connecting to the database.')),
    );
  }

}
