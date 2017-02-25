<?php

final class PhabricatorDaemonManagementRestartWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('restart')
      ->setSynopsis(pht('Stop, then start the standard daemon loadout.'))
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
            'name' => 'gently',
            'help' => pht(
              'Ignore running processes that look like daemons but do not '.
              'have corresponding PID files.'),
          ),
          array(
            'name' => 'force',
            'help' => pht(
              'Also stop running processes that look like daemons but do '.
              'not have corresponding PID files.'),
          ),
          $this->getAutoscaleReserveArgument(),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $err = $this->executeStopCommand(
      array(),
      array(
        'graceful' => $args->getArg('graceful'),
        'force' => $args->getArg('force'),
        'gently' => $args->getArg('gently'),
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
