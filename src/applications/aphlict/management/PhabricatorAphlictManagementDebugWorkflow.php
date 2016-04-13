<?php

final class PhabricatorAphlictManagementDebugWorkflow
  extends PhabricatorAphlictManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('debug')
      ->setSynopsis(
        pht(
          'Start the notifications server in the foreground and print large '.
          'volumes of diagnostic information to the console.'))
      ->setArguments($this->getLaunchArguments());
  }

  public function execute(PhutilArgumentParser $args) {
    $this->parseLaunchArguments($args);

    $this->setDebug(true);

    $this->willLaunch();
    return $this->launch();
  }

}
