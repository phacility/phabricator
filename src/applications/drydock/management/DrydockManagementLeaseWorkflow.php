<?php

final class DrydockManagementLeaseWorkflow
  extends DrydockManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('lease')
      ->setSynopsis('Lease a resource.')
      ->setArguments(
        array(
          array(
            'name'      => 'type',
            'param'     => 'resource_type',
            'help'      => 'Resource type.',
          ),
          array(
            'name'      => 'attributes',
            'param'     => 'name=value,...',
            'help'      => 'Resource specficiation.',
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $resource_type = $args->getArg('type');
    if (!$resource_type) {
      throw new PhutilArgumentUsageException(
        'Specify a resource type with `--type`.');
    }

    $attributes = $args->getArg('attributes');
    if ($attributes) {
      $options = new PhutilSimpleOptions();
      $options->setCaseSensitive(true);
      $attributes = $options->parse($attributes);
    }

    PhabricatorWorker::setRunAllTasksInProcess(true);

    $lease = id(new DrydockLease())
      ->setResourceType($resource_type);
    if ($attributes) {
      $lease->setAttributes($attributes);
    }
    $lease
      ->queueForActivation()
      ->waitUntilActive();

    $console->writeOut("Acquired Lease %s\n", $lease->getID());
    return 0;
  }

}
