<?php

final class PhabricatorDaemonManagementReloadWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('reload')
      ->setSynopsis(
        pht(
          'Gracefully restart daemon processes in-place to pick up changes '.
          'to source. This will not disrupt running jobs. This is an '.
          'advanced workflow; most installs should use __%s__.',
          'phd restart'))
      ->setArguments(
        array(
          array(
            'name' => 'pids',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    return $this->executeReloadCommand($args->getArg('pids'));
  }

}
