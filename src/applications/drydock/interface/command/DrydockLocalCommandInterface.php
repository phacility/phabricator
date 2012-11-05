<?php

final class DrydockLocalCommandInterface extends DrydockCommandInterface {

  public function getExecFuture($command) {
    $argv = func_get_args();
    return newv('ExecFuture', $argv);
  }

}
