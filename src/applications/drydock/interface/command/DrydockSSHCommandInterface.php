<?php

final class DrydockSSHCommandInterface extends DrydockCommandInterface {

  private $passphraseSSHKey;

  private function openCredentialsIfNotOpen() {
    if ($this->passphraseSSHKey !== null) {
      return;
    }

    $credential = id(new PassphraseCredentialQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withIDs(array($this->getConfig('credential')))
      ->needSecrets(true)
      ->executeOne();

    if ($credential->getProvidesType() !==
      PassphraseCredentialTypeSSHPrivateKey::PROVIDES_TYPE) {
      throw new Exception("Only private key credentials are supported.");
    }

    $this->passphraseSSHKey = PassphraseSSHKey::loadFromPHID(
      $credential->getPHID(),
      PhabricatorUser::getOmnipotentUser());
  }

  public function getExecFuture($command) {
    $this->openCredentialsIfNotOpen();

    $argv = func_get_args();

    // This assumes there's a UNIX shell living at the other
    // end of the connection, which isn't the case for Windows machines.
    if ($this->getConfig('platform') !== 'windows') {
      $argv = $this->applyWorkingDirectoryToArgv($argv);
    }

    $full_command = call_user_func_array('csprintf', $argv);

    if ($this->getConfig('platform') === 'windows') {
      // On Windows platforms we need to execute cmd.exe explicitly since
      // most commands are not really executables.
      $full_command = 'C:\\Windows\\system32\\cmd.exe /C '.$full_command;
    }

    return new ExecFuture(
      'ssh -o StrictHostKeyChecking=no -p %s -i %P %P@%s -- %s',
      $this->getConfig('port'),
      $this->passphraseSSHKey->getKeyfileEnvelope(),
      $this->passphraseSSHKey->getUsernameEnvelope(),
      $this->getConfig('host'),
      $full_command);
  }
}
