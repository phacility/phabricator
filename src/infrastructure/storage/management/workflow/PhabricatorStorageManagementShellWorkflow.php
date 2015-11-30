<?php

final class PhabricatorStorageManagementShellWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('shell')
      ->setExamples('**shell** [__options__]')
      ->setSynopsis(pht('Launch an interactive shell.'));
  }

  public function execute(PhutilArgumentParser $args) {


    $api = $this->getAPI();
    list($host, $port) = $this->getBareHostAndPort($api->getHost());

    $flag_port = $port
      ? csprintf('--port %d', $port)
      : '';

    $flag_password = '';
    $password = $api->getPassword();
    if ($password) {
      if (strlen($password->openEnvelope())) {
        $flag_password = csprintf('--password=%P', $password);
      }
    }

    return phutil_passthru(
      'mysql --default-character-set=utf8 '.
      '-u %s %C -h %s %C',
      $api->getUser(),
      $flag_password,
      $host,
      $flag_port);
  }

}
