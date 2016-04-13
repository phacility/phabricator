<?php

final class PhabricatorAphlictManagementStopWorkflow
  extends PhabricatorAphlictManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('stop')
      ->setSynopsis(pht('Stop the notification server.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    return $this->executeStopCommand();
  }

}
