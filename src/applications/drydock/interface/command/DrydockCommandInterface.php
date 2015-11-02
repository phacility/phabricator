<?php

abstract class DrydockCommandInterface extends DrydockInterface {

  const INTERFACE_TYPE = 'command';

  private $workingDirectoryStack = array();

  public function pushWorkingDirectory($working_directory) {
    $this->workingDirectoryStack[] = $working_directory;
    return $this;
  }

  public function popWorkingDirectory() {
    if (!$this->workingDirectoryStack) {
      throw new Exception(
        pht(
          'Unable to pop working directory, directory stack is empty.'));
    }
    return array_pop($this->workingDirectoryStack);
  }

  public function peekWorkingDirectory() {
    if ($this->workingDirectoryStack) {
      return last($this->workingDirectoryStack);
    }
    return null;
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
    $directory = $this->peekWorkingDirectory();

    if ($directory !== null) {
      $cmd = $argv[0];
      $cmd = "(cd %s && {$cmd})";
      $argv = array_merge(
        array($cmd),
        array($directory),
        array_slice($argv, 1));
    }

    return $argv;
  }

}
