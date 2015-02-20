<?php

final class PhabricatorDaemonManagementListWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('list')
      ->setSynopsis(pht('Show a list of available daemons.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $symbols = $this->loadAvailableDaemonClasses();
    $symbols = igroup($symbols, 'library');

    foreach ($symbols as $library => $symbol_list) {
      $console->writeOut(pht('Daemons in library __%s__:', $library)."\n");
      foreach ($symbol_list as $symbol) {
        $console->writeOut("    %s\n", $symbol['name']);
      }
      $console->writeOut("\n");
    }

    return 0;
  }


}
