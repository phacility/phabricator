<?php

final class PhabricatorAphlictManagementDebugWorkflow
  extends PhabricatorAphlictManagementWorkflow {

  protected function didConstruct() {
    parent::didConstruct();
    $this
      ->setName('debug')
      ->setSynopsis(
        pht(
          'Start the notifications server in the foreground and print large '.
          'volumes of diagnostic information to the console.'));
  }

  public function execute(PhutilArgumentParser $args) {
    parent::execute($args);
    $this->setDebug(true);

    $this->willLaunch();
    return $this->launch();
  }

}
