<?php

final class PhabricatorAphlictManagementRestartWorkflow
  extends PhabricatorAphlictManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('restart')
      ->setSynopsis(pht('Stop, then start the notifications server.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $err = $this->executeStopCommand();
    if ($err) {
      return $err;
    }
    return $this->executeStartCommand();
  }

}
