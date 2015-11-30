<?php

final class PhabricatorStorageManagementDumpWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('dump')
      ->setExamples('**dump** [__options__]')
      ->setSynopsis(pht('Dump all data in storage to stdout.'));
  }

  public function didExecute(PhutilArgumentParser $args) {
    $api     = $this->getAPI();
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

    $flag_password = '';
    $password = $api->getPassword();
    if ($password) {
      if (strlen($password->openEnvelope())) {
        $flag_password = csprintf('-p%P', $password);
      }
    }

    $flag_port = $port
      ? csprintf('--port %d', $port)
      : '';

    return phutil_passthru(
      'mysqldump --hex-blob --single-transaction --default-character-set=utf8 '.
      '-u %s %C -h %s %C --databases %Ls',
      $api->getUser(),
      $flag_password,
      $host,
      $flag_port,
      $databases);
  }

}
