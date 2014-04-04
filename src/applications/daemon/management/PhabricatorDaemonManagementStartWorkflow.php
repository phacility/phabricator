<?php

final class PhabricatorDaemonManagementStartWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('start')
      ->setSynopsis(
        pht(
          'Start the standard configured collection of Phabricator daemons. '.
          'This is appropriate for most installs. Use **phd launch** to '.
          'customize which daemons are launched.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    return $this->executeStartCommand();
  }

}
