<?php

abstract class DrydockCommandInterface extends DrydockInterface {

  const INTERFACE_TYPE = 'command';

  private $workingDirectory;

  public function setWorkingDirectory($working_directory) {
    $this->workingDirectory = $working_directory;
    return $this;
  }

  public function getWorkingDirectory() {
    return $this->workingDirectory;
  }

  final public function getInterfaceType() {
    return self::INTERFACE_TYPE;
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

  protected function applyWorkingDirectoryToArgv(array $argv) {
    if ($this->getWorkingDirectory() !== null) {
      $cmd = $argv[0];
      $cmd = "(cd %s && {$cmd})";
      $argv = array_merge(
        array($cmd),
        array($this->getWorkingDirectory()),
        array_slice($argv, 1));
    }

    return $argv;
  }

}
