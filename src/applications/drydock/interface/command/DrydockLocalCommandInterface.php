<?php

final class DrydockLocalCommandInterface extends DrydockCommandInterface {

  public function getExecFuture($command) {
    $argv = func_get_args();
    $argv = $this->applyWorkingDirectoryToArgv($argv);

    return newv('ExecFuture', $argv);
  }

}
