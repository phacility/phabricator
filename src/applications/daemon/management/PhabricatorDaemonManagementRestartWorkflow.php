<?php

final class PhabricatorDaemonManagementRestartWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('restart')
      ->setSynopsis(
        pht(
          'Stop daemon processes on this host, then start the standard '.
          'daemon loadout.'))
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
          $this->getAutoscaleReserveArgument(),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $err = $this->executeStopCommand(
      array(
        'graceful' => $args->getArg('graceful'),
        'force' => $args->getArg('force'),
      ));

    if ($err) {
      return $err;
    }

    return $this->executeStartCommand(
      array(
        'reserve' => (float)$args->getArg('autoscale-reserve'),
      ));
  }

}
