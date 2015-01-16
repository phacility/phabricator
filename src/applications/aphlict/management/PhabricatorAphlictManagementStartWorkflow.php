<?php

final class PhabricatorAphlictManagementStartWorkflow
  extends PhabricatorAphlictManagementWorkflow {

  protected function didConstruct() {
    parent::didConstruct();
    $this
      ->setName('start')
      ->setSynopsis(pht('Start the notifications server.'));
  }

  public function execute(PhutilArgumentParser $args) {
    parent::execute($args);
    return $this->executeStartCommand();
  }

}
