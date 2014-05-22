<?php

final class PhabricatorDaemonManagementStatusWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('status')
      ->setSynopsis(pht('Show status of running daemons.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $daemons = $this->loadRunningDaemons();

    if (!$daemons) {
      $console->writeErr(
        "%s\n",
        pht("There are no running Phabricator daemons."));
      return 1;
    }

    $status = 0;
    printf(
      "%-5s\t%-24s\t%-50s%s\n",
      'PID',
      'Started',
      'Daemon',
      'Arguments');
    foreach ($daemons as $daemon) {
      $name = $daemon->getName();
      if (!$daemon->isRunning()) {
        $daemon->updateStatus(PhabricatorDaemonLog::STATUS_DEAD);
        $status = 2;
        $name = '<DEAD> '.$name;
      }
      printf(
        "%5s\t%-24s\t%-50s%s\n",
        $daemon->getPID(),
        $daemon->getEpochStarted()
          ? date('M j Y, g:i:s A', $daemon->getEpochStarted())
          : null,
        $name,
        csprintf('%LR', $daemon->getArgv()));
    }

    return $status;
  }


}
