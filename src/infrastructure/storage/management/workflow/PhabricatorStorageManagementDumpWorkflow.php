<?php

final class PhabricatorStorageManagementDumpWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('dump')
      ->setExamples('**dump** [__options__]')
      ->setSynopsis(pht('Dump all data in storage to stdout.'))
      ->setArguments(
        array(
          array(
            'name' => 'for-replica',
            'help' => pht(
              'Add __--master-data__ to the __mysqldump__ command, '.
              'generating a CHANGE MASTER statement in the output.'),
          ),
        ));
  }

  protected function isReadOnlyWorkflow() {
    return true;
  }

  public function didExecute(PhutilArgumentParser $args) {
    $api = $this->getAPI();
    $patches = $this->getPatches();

    $console = PhutilConsole::getConsole();

    $applied = $api->getAppliedPatches();
    if ($applied === null) {
      $namespace = $api->getNamespace();
      $console->writeErr(
        pht(
          '**Storage Not Initialized**: There is no database storage '.
          'initialized in this storage namespace ("%s"). Use '.
          '**%s** to initialize storage.',
          $namespace,
          './bin/storage upgrade'));
      return 1;
    }

    $databases = $api->getDatabaseList($patches, true);

    list($host, $port) = $this->getBareHostAndPort($api->getHost());

    $has_password = false;

    $password = $api->getPassword();
    if ($password) {
      if (strlen($password->openEnvelope())) {
        $has_password = true;
      }
    }

    $argv = array();
    $argv[] = '--hex-blob';
    $argv[] = '--single-transaction';
    $argv[] = '--default-character-set=utf8';

    if ($args->getArg('for-replica')) {
      $argv[] = '--master-data';
    }

    $argv[] = '-u';
    $argv[] = $api->getUser();
    $argv[] = '-h';
    $argv[] = $host;

    if ($port) {
      $argv[] = '--port';
      $argv[] = $port;
    }

    $argv[] = '--databases';
    foreach ($databases as $database) {
      $argv[] = $database;
    }

    if ($has_password) {
      $err = phutil_passthru('mysqldump -p%P %Ls', $password, $argv);
    } else {
      $err = phutil_passthru('mysqldump %Ls', $argv);
    }

    return $err;
  }

}
