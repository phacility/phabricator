<?php

final class PhabricatorAphlictManagementStopWorkflow
  extends PhabricatorAphlictManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('stop')
      ->setSynopsis(pht('Stop the notifications server.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    return $this->executeStopCommand();
  }

}
