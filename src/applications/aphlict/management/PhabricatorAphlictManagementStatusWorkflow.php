<?php

final class PhabricatorAphlictManagementStatusWorkflow
  extends PhabricatorAphlictManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('status')
      ->setSynopsis(pht('Show the status of the notification server.'))
      ->setArguments($this->getLaunchArguments());
  }

  public function execute(PhutilArgumentParser $args) {
    $this->parseLaunchArguments($args);
    $console = PhutilConsole::getConsole();
    $pid = $this->getPID();

    if (!$pid) {
      $console->writeErr("%s\n", pht('Aphlict is not running.'));
      return 1;
    }

    $console->writeOut("%s\n", pht('Aphlict (%s) is running.', $pid));
    return 0;
  }

}
