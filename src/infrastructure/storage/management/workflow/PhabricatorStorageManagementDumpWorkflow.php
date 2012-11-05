<?php

final class PhabricatorStorageManagementDumpWorkflow
  extends PhabricatorStorageManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('dump')
      ->setExamples('**dump** [__options__]')
      ->setSynopsis('Dump all data in storage to stdout.');
  }

  public function execute(PhutilArgumentParser $args) {
    $api = $this->getAPI();
    $patches = $this->getPatches();

    $applied = $api->getAppliedPatches();
    if ($applied === null) {
      $namespace = $api->getNamespace();
      echo phutil_console_wrap(
        phutil_console_format(
          "**No Storage**: There is no database storage initialized in this ".
          "storage namespace ('{$namespace}'). Use '**storage upgrade**' to ".
          "initialize storage.\n"));
      return 1;
    }

    $databases = $api->getDatabaseList($patches);

    list($host, $port) = $this->getBareHostAndPort($api->getHost());

    $flag_password = '';

    $password = $api->getPassword();
    if ($password) {
      $password = $password->openEnvelope();
      if (strlen($password)) {
        $flag_password = csprintf('-p%s', $password);
      }
    }

    $flag_port = $port
      ? csprintf('--port %d', $port)
      : '';

    return phutil_passthru(

      'mysqldump --default-character-set=utf8 '.
      '-u %s %C -h %s %C --databases %Ls',

      $api->getUser(),
      $flag_password,
      $host,
      $flag_port,
      $databases);
  }

  private function getBareHostAndPort($host) {
    // Split out port information, since the command-line client requires a
    // separate flag for the port.
    $uri = new PhutilURI('mysql://'.$host);
    if ($uri->getPort()) {
      $port = $uri->getPort();
      $bare_hostname = $uri->getDomain();
    } else {
      $port = null;
      $bare_hostname = $host;
    }

    return array($bare_hostname, $port);
  }

}
