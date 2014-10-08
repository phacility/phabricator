<?php

final class PhabricatorAphlictManagementStartWorkflow
  extends PhabricatorAphlictManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('start')
      ->setSynopsis(pht('Start the notifications server.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    return $this->executeStartCommand();
  }

}
