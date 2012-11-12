<?php

abstract class DrydockCommandInterface extends DrydockInterface {

  final public function getInterfaceType() {
    return 'command';
  }

  final public function exec($command) {
    $argv = func_get_args();
    $exec = call_user_func_array(
      array($this, 'getExecFuture'),
      $argv);
    return $exec->resolve();
  }

  final public function execx($command) {
    $argv = func_get_args();
    $exec = call_user_func_array(
      array($this, 'getExecFuture'),
      $argv);
    return $exec->resolvex();
  }

  abstract public function getExecFuture($command);

}
