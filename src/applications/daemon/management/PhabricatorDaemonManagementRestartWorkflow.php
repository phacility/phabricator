<?php

final class PhabricatorDaemonManagementRestartWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('restart')
      ->setSynopsis(
        pht(
          'Stop, then start the standard daemon loadout.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $err = $this->executeStopCommand(array());
    if ($err) {
      return $err;
    }
    return $this->executeStartCommand();
  }

}
