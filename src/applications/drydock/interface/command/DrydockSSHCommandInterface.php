<?php

final class DrydockSSHCommandInterface extends DrydockCommandInterface {

  private $credential;
  private $connectTimeout;

  private function loadCredential() {
    if ($this->credential === null) {
      $credential_phid = $this->getConfig('credentialPHID');

      $this->credential = PassphraseSSHKey::loadFromPHID(
        $credential_phid,
        PhabricatorUser::getOmnipotentUser());
    }

    return $this->credential;
  }

  public function setConnectTimeout($timeout) {
    $this->connectTimeout = $timeout;
    return $this;
  }

  public function getExecFuture($command) {
    $credential = $this->loadCredential();

    $argv = func_get_args();
    $argv = $this->applyWorkingDirectoryToArgv($argv);
    $full_command = call_user_func_array('csprintf', $argv);

    $flags = array();
    $flags[] = '-o';
    $flags[] = 'LogLevel=quiet';

    $flags[] = '-o';
    $flags[] = 'StrictHostKeyChecking=no';

    $flags[] = '-o';
    $flags[] = 'UserKnownHostsFile=/dev/null';

    $flags[] = '-o';
    $flags[] = 'BatchMode=yes';

    if ($this->connectTimeout) {
      $flags[] = '-o';
      $flags[] = 'ConnectTimeout='.$this->connectTimeout;
    }

    return new ExecFuture(
      'ssh %Ls -l %P -p %s -i %P %s -- %s',
      $flags,
      $credential->getUsernameEnvelope(),
      $this->getConfig('port'),
      $credential->getKeyfileEnvelope(),
      $this->getConfig('host'),
      $full_command);
  }
}
