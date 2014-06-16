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
        pht('There are no running Phabricator daemons.'));
      return 1;
    }

    $status = 0;
    $table = id(new PhutilConsoleTable())
      ->addColumns(array(
        'pid' => array(
          'title' => 'PID',
        ),
        'started' => array(
          'title' => 'Started',
        ),
        'daemon' => array(
          'title' => 'Daemon',
        ),
        'argv' => array(
          'title' => 'Arguments',
        ),
      ));

    foreach ($daemons as $daemon) {
      $name = $daemon->getName();
      if (!$daemon->isRunning()) {
        $daemon->updateStatus(PhabricatorDaemonLog::STATUS_DEAD);
        $status = 2;
        $name = '<DEAD> '.$name;
      }

      $table->addRow(array(
        'pid'     => $daemon->getPID(),
        'started' => $daemon->getEpochStarted()
          ? date('M j Y, g:i:s A', $daemon->getEpochStarted())
          : null,
        'daemon'  => $name,
        'argv'    => csprintf('%LR', $daemon->getArgv()),
      ));
    }

    $table->draw();
  }

  protected function executeGlobal() {
    $console = PhutilConsole::getConsole();
    $daemons = $this->loadAllRunningDaemons();

    if (!$daemons) {
      $console->writeErr(
        "%s\n",
        pht('There are no running Phabricator daemons.'));
      return 1;
    }

    $status = 0;

    $table = id(new PhutilConsoleTable())
      ->addColumns(array(
        'id' => array(
          'title' => 'ID',
        ),
        'host' => array(
          'title' => 'Host',
        ),
        'pid' => array(
          'title' => 'PID',
        ),
        'started' => array(
          'title' => 'Started',
        ),
        'daemon' => array(
          'title' => 'Daemon',
        ),
        'argv' => array(
          'title' => 'Arguments',
        ),
      ));

    foreach ($daemons as $daemon) {
      $table->addRow(array(
        'id'      => $daemon->getID(),
        'host'    => $daemon->getHost(),
        'pid'     => $daemon->getPID(),
        'started' => date('M j Y, g:i:s A', $daemon->getDateCreated()),
        'daemon'  => $daemon->getDaemon(),
        'argv'    => csprintf('%LR', array() /* $daemon->getArgv() */),
      ));
    }

    $table->draw();
  }

}
