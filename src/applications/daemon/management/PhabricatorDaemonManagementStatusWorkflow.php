<?php

final class PhabricatorDaemonManagementStatusWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('status')
      ->setSynopsis(pht('Show status of running daemons.'))
      ->setArguments(
        array(
          array(
            'name' => 'local',
            'help' => pht('Show only local daemons.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    if ($args->getArg('local')) {
      $daemons = $this->loadRunningDaemons();
    } else {
      $daemons = $this->loadAllRunningDaemons();
    }

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
          'title' => pht('Log'),
        ),
        'daemonID' => array(
          'title' => pht('Daemon'),
        ),
        'host' => array(
          'title' => pht('Host'),
        ),
        'pid' => array(
          'title' => pht('Overseer'),
        ),
        'started' => array(
          'title' => pht('Started'),
        ),
        'daemon' => array(
          'title' => pht('Class'),
        ),
        'argv' => array(
          'title' => pht('Arguments'),
        ),
      ));

    foreach ($daemons as $daemon) {
      if ($daemon instanceof PhabricatorDaemonLog) {
        $table->addRow(array(
          'id'      => $daemon->getID(),
          'daemonID' => $daemon->getDaemonID(),
          'host'    => $daemon->getHost(),
          'pid'     => $daemon->getPID(),
          'started' => date('M j Y, g:i:s A', $daemon->getDateCreated()),
          'daemon'  => $daemon->getDaemon(),
          'argv'    => csprintf('%LR', $daemon->getExplicitArgv()),
        ));
      } else if ($daemon instanceof PhabricatorDaemonReference) {
        $name = $daemon->getName();
        if (!$daemon->isRunning()) {
          $daemon->updateStatus(PhabricatorDaemonLog::STATUS_DEAD);
          $status = 2;
          $name = '<DEAD> '.$name;
        }

        $daemon_log = $daemon->getDaemonLog();
        $id = null;
        $daemon_id = null;
        if ($daemon_log) {
          $id = $daemon_log->getID();
          $daemon_id = $daemon_log->getDaemonID();
        }

        $table->addRow(array(
          'id'      => $id,
          'daemonID' => $daemon_id,
          'host'    => 'localhost',
          'pid'     => $daemon->getPID(),
          'started' => $daemon->getEpochStarted()
            ? date('M j Y, g:i:s A', $daemon->getEpochStarted())
            : null,
          'daemon'  => $name,
          'argv'    => csprintf('%LR', $daemon->getArgv()),
        ));
      }
    }

    $table->draw();
  }

}
