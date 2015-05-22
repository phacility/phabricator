<?php

final class DrydockSSHCommandInterface extends DrydockCommandInterface {

  private $passphraseSSHKey;
  private $connectTimeout;

  private function openCredentialsIfNotOpen() {
    if ($this->passphraseSSHKey !== null) {
      return;
    }

    $credential = id(new PassphraseCredentialQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withIDs(array($this->getConfig('credential')))
      ->needSecrets(true)
      ->executeOne();

    if ($credential === null) {
      throw new Exception(
        pht(
          'There is no credential with ID %d.',
          $this->getConfig('credential')));
    }

    if ($credential->getProvidesType() !==
      PassphraseCredentialTypeSSHPrivateKey::PROVIDES_TYPE) {
      throw new Exception(pht('Only private key credentials are supported.'));
    }

    $this->passphraseSSHKey = PassphraseSSHKey::loadFromPHID(
      $credential->getPHID(),
      PhabricatorUser::getOmnipotentUser());
  }

  public function setConnectTimeout($timeout) {
    $this->connectTimeout = $timeout;
    return $this;
  }

  public function getExecFuture($command) {
    $this->openCredentialsIfNotOpen();

    $argv = func_get_args();

    if ($this->getConfig('platform') === 'windows') {
      // Handle Windows by executing the command under PowerShell.
      $command = id(new PhutilCommandString($argv))
        ->setEscapingMode(PhutilCommandString::MODE_POWERSHELL);

      $change_directory = '';
      if ($this->getWorkingDirectory() !== null) {
        $change_directory .= 'cd '.$this->getWorkingDirectory();
      }

      $script = <<<EOF
$change_directory
$command
if (\$LastExitCode -ne 0) {
  exit \$LastExitCode
}
EOF;

      // When Microsoft says "Unicode" they don't mean UTF-8.
      $script = mb_convert_encoding($script, 'UTF-16LE');

      $script = base64_encode($script);

      $powershell =
        'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
      $powershell .=
        ' -ExecutionPolicy Bypass'.
        ' -NonInteractive'.
        ' -InputFormat Text'.
        ' -OutputFormat Text'.
        ' -EncodedCommand '.$script;

      $full_command = $powershell;
    } else {
      // Handle UNIX by executing under the native shell.
      $argv = $this->applyWorkingDirectoryToArgv($argv);

      $full_command = call_user_func_array('csprintf', $argv);
    }

    $command_timeout = '';
    if ($this->connectTimeout !== null) {
      $command_timeout = csprintf(
        '-o %s',
        'ConnectTimeout='.$this->connectTimeout);
    }

    return new ExecFuture(
      'ssh '.
      '-o LogLevel=quiet '.
      '-o StrictHostKeyChecking=no '.
      '-o UserKnownHostsFile=/dev/null '.
      '-o BatchMode=yes '.
      '%C -p %s -i %P %P@%s -- %s',
      $command_timeout,
      $this->getConfig('port'),
      $this->passphraseSSHKey->getKeyfileEnvelope(),
      $this->passphraseSSHKey->getUsernameEnvelope(),
      $this->getConfig('host'),
      $full_command);
  }
}
