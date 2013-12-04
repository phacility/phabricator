<?php

final class DrydockSSHCommandInterface extends DrydockCommandInterface {

  public function getExecFuture($command) {
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

    // NOTE: The "-t -t" is for psuedo-tty allocation so we can "sudo" on some
    // systems, but maybe more trouble than it's worth?

    $credential = id(new PassphraseCredentialQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withIDs(array($this->getConfig('credential')))
      ->needSecrets(true)
      ->executeOne();

    // FIXME: We can't use text-based SSH files here because the TempFile goes
    // out of scope after this function ends and thus the file gets removed
    // before it can be used.
    if ($credential->getCredentialType() !==
      PassphraseCredentialTypeSSHPrivateKeyFile::CREDENTIAL_TYPE) {
      throw new Exception("Only private key file credentials are supported.");
    }

    $ssh_key = PassphraseSSHKey::loadFromPHID(
      $credential->getPHID(),
      PhabricatorUser::getOmnipotentUser());

    return new ExecFuture(
      'ssh -t -t -o StrictHostKeyChecking=no -p %s -i %s %s@%s -- %s',
      $this->getConfig('port'),
      $ssh_key->getKeyfileEnvelope()->openEnvelope(),
      $credential->getUsername(),
      $this->getConfig('host'),
      $full_command);
  }

}
