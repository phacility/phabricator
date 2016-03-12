<?php

final class DrydockCommandError extends Phobject {

  private $phase;
  private $displayCommand;
  private $command;
  private $error;
  private $stdout;
  private $stderr;

  public static function newFromCommandException(CommandException $ex) {
    $error = new self();

    $error->command = (string)$ex->getCommand();

    $error->error = $ex->getError();
    $error->stdout = $ex->getStdout();
    $error->stderr = $ex->getStderr();

    return $error;
  }

  public function setPhase($phase) {
    $this->phase = $phase;
    return $this;
  }

  public function getPhase() {
    return $this->phase;
  }

  public function setDisplayCommand($display_command) {
    $this->displayCommand = (string)$display_command;
    return $this;
  }

  public function getDisplayCommand() {
    return $this->displayCommand;
  }

  public function toDictionary() {
    $display_command = $this->getDisplayCommand();
    if ($display_command === null) {
      $display_command = $this->command;
    }

    return array(
      'phase' => $this->getPhase(),
      'command' => $display_command,
      'raw' => $this->command,
      'err' => $this->error,
      'stdout' => $this->stdout,
      'stderr' => $this->stderr,
    );
  }

}
