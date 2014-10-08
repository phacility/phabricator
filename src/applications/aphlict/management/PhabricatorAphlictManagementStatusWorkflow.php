<?php

final class PhabricatorAphlictManagementStatusWorkflow
  extends PhabricatorAphlictManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('status')
      ->setSynopsis(pht('Show the status of the notifications server.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $pid = $this->getPID();

    if (!$pid) {
      $console->writeErr(pht("Aphlict is not running.\n"));
      return 1;
    }

    $console->writeOut(pht("Aphlict (%s) is running.\n", $pid));
    return 0;
  }

}
