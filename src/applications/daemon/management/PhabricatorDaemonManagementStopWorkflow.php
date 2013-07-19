<?php

final class PhabricatorDaemonManagementStopWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('stop')
      ->setSynopsis(
        pht(
          'Stop all running daemons, or specific daemons identified by PIDs. '.
          'Use **phd status** to find PIDs.'))
      ->setArguments(
        array(
          array(
            'name' => 'pids',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $pids = $args->getArg('pids');
    return $this->executeStopCommand($pids);
  }

}
