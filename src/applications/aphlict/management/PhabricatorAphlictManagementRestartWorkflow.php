<?php

final class PhabricatorAphlictManagementRestartWorkflow
  extends PhabricatorAphlictManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('restart')
      ->setSynopsis(pht('Stop, then start the notification server.'))
      ->setArguments($this->getLaunchArguments());
  }

  public function execute(PhutilArgumentParser $args) {
    $this->parseLaunchArguments($args);

    $err = $this->executeStopCommand();
    if ($err) {
      return $err;
    }

    return $this->executeStartCommand();
  }

}
