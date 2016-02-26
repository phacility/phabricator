<?php

class DrydockCommandError {
  public static function newFromCommandException(
    $phase,
    $command,
    CommandException $ex) {
    $error = array(
      'phase' => $phase,
      'command' => (string)$command,
      'raw' => (string)$ex->getCommand(),
      'err' => $ex->getError(),
      'stdout' => $ex->getStdout(),
      'stderr' => $ex->getStderr(),
    );
    return $error;
  }
}
