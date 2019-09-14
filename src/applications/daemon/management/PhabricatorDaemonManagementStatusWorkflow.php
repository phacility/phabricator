<?php

final class PhabricatorDaemonManagementStatusWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('status')
      ->setSynopsis(pht('Show daemon processes on this host.'));
  }

  public function execute(PhutilArgumentParser $args) {
    $process_refs = $this->getOverseerProcessRefs();

    if (!$process_refs) {
      $instance = $this->getInstance();
      if ($instance !== null) {
        $this->logInfo(
          pht('NO DAEMONS'),
          pht(
            'There are no running daemon processes for the current '.
            'instance ("%s").',
            $instance));
      } else {
        $this->logInfo(
          pht('NO DAEMONS'),
          pht('There are no running daemon processes.'));
      }

      return 1;
    }

    $table = id(new PhutilConsoleTable())
      ->addColumns(
        array(
          'pid' => array(
            'title' => pht('PID'),
          ),
          'command' => array(
            'title' => pht('Command'),
          ),
        ));

    foreach ($process_refs as $process_ref) {
      $table->addRow(
        array(
          'pid' => $process_ref->getPID(),
          'command' => $process_ref->getCommand(),
        ));
    }

    $table->draw();

    return 0;
  }

}
