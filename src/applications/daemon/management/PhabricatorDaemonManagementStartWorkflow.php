<?php

final class PhabricatorDaemonManagementStartWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('start')
      ->setSynopsis(
        pht(
          'Start the standard configured collection of daemons. '.
          'This is appropriate for most installs. Use **%s** to '.
          'customize which daemons are launched.',
          'phd launch'))
      ->setArguments(
        array(
          array(
            'name' => 'keep-leases',
            'help' => pht(
              'By default, **%s** will free all task leases held by '.
              'the daemons. With this flag, this step will be skipped.',
              'phd start'),
          ),
          array(
            'name' => 'force',
            'help' => pht('Start daemons even if daemons are already running.'),
          ),
          $this->getAutoscaleReserveArgument(),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    return $this->executeStartCommand(
      array(
        'keep-leases' => $args->getArg('keep-leases'),
        'force' => $args->getArg('force'),
        'reserve' => (float)$args->getArg('autoscale-reserve'),
      ));
  }

}
