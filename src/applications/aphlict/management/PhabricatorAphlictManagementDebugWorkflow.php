<?php

final class PhabricatorAphlictManagementDebugWorkflow
  extends PhabricatorAphlictManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('debug')
      ->setSynopsis(
        pht(
          'Start the notifications server in the foreground and print large '.
          'volumes of diagnostic information to the console.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $this->willLaunch();
    return $this->launch(true);
  }

}
