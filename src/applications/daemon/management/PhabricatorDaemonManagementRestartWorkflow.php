<?php

final class PhabricatorDaemonManagementRestartWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('restart')
      ->setSynopsis(
        pht(
          'Stop, then start the standard daemon loadout.'))
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
              'Also stop running processes that look like daemons but do '.
              'not have corresponding PID files.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $graceful = $args->getArg('graceful');
    $force = $args->getArg('force');
    $err = $this->executeStopCommand(array(), $graceful, $force);
    if ($err) {
      return $err;
    }
    return $this->executeStartCommand();
  }

}
