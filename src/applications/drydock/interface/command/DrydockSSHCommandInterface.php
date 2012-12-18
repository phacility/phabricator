<?php

final class DrydockSSHCommandInterface extends DrydockCommandInterface {

  public function getExecFuture($command) {
    $argv = func_get_args();
    $argv = $this->applyWorkingDirectoryToArgv($argv);

    $full_command = call_user_func_array('csprintf', $argv);

    // NOTE: The "-t -t" is for psuedo-tty allocation so we can "sudo" on some
    // systems, but maybe more trouble than it's worth?

    return new ExecFuture(
      'ssh -t -t -o StrictHostKeyChecking=no -i %s %s@%s -- %s',
      $this->getConfig('ssh-keyfile'),
      $this->getConfig('user'),
      $this->getConfig('host'),
      $full_command);
  }

}
