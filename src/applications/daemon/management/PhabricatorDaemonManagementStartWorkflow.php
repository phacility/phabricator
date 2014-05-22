<?php

final class PhabricatorDaemonManagementStartWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('start')
      ->setSynopsis(
        pht(
          'Start the standard configured collection of Phabricator daemons. '.
          'This is appropriate for most installs. Use **phd launch** to '.
          'customize which daemons are launched.'))
      ->setArguments(
        array(
          array(
            'name' => 'keep-leases',
            'help' => pht(
              'By default, **phd start** will free all task leases held by '.
              'the daemons. With this flag, this step will be skipped.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    return $this->executeStartCommand($args->getArg('keep-leases'));
  }

}
