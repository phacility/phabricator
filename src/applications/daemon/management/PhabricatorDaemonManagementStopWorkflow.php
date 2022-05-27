<?php

final class PhabricatorDaemonManagementStopWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('stop')
      ->setSynopsis(pht('Stop daemon processes on this host.'))
      ->setArguments(
        array(
          array(
            'name' => 'graceful',
            'param' => 'seconds',
            'help' => pht(
              'Grace period for daemons to attempt a clean shutdown, in '.
              'seconds. Defaults to __15__ seconds.'),
            'default' => 15,
          ),
          array(
            'name' => 'force',
            'help' => pht(
              'Stop all daemon processes on this host, even if they belong '.
              'to another instance.'),
          ),
          array(
            'name' => 'gently',
            'help' => pht('Deprecated. Has no effect.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    return $this->executeStopCommand(
      array(
        'graceful' => $args->getArg('graceful'),
        'force' => $args->getArg('force'),
      ));
  }

}
