<?php

final class PhabricatorAphlictManagementRestartWorkflow
  extends PhabricatorAphlictManagementWorkflow {

  protected function didConstruct() {
    parent::didConstruct();
    $this
      ->setName('restart')
      ->setSynopsis(pht('Stop, then start the notifications server.'));
  }

  public function execute(PhutilArgumentParser $args) {
    parent::execute($args);

    $err = $this->executeStopCommand();
    if ($err) {
      return $err;
    }
    return $this->executeStartCommand();
  }

}
