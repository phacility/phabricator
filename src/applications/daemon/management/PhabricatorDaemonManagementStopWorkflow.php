<?php

final class PhabricatorDaemonManagementStopWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('stop')
      ->setSynopsis(
        pht(
          'Stop all running daemons, or specific daemons identified by PIDs. '.
          'Use **%s** to find PIDs.',
          'phd status'))
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
          array(
            'name' => 'gently',
            'help' => pht(
              'Ignore running processes that look like daemons but do not '.
              'have corresponding PID files.'),
          ),
          array(
            'name' => 'pids',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    return $this->executeStopCommand(
      $args->getArg('pids'),
      array(
        'graceful' => $args->getArg('graceful'),
        'force' => $args->getArg('force'),
        'gently' => $args->getArg('gently'),
      ));
  }

}
