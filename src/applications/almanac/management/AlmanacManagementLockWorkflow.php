<?php

final class AlmanacManagementLockWorkflow
  extends AlmanacManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('lock')
      ->setSynopsis(pht('Lock a service to prevent it from being edited.'))
      ->setArguments(
        array(
          array(
            'name' => 'services',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $services = $this->loadServices($args->getArg('services'));
    if (!$services) {
      throw new PhutilArgumentUsageException(
        pht('Specify at least one service to lock.'));
    }

    foreach ($services as $service) {
      if ($service->getIsLocked()) {
        throw new PhutilArgumentUsageException(
          pht(
            'Service "%s" is already locked!',
            $service->getName()));
      }
    }

    foreach ($services as $service) {
      $this->updateServiceLock($service, true);

      $console->writeOut(
        "**<bg:green> %s </bg>** %s\n",
        pht('LOCKED'),
        pht('Service "%s" was locked.', $service->getName()));
    }

    return 0;
  }

}
