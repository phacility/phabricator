<?php

final class AlmanacManagementUnlockWorkflow
  extends AlmanacManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('unlock')
      ->setSynopsis(pht('Unlock a service to allow it to be edited.'))
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
        pht('Specify at least one service to unlock.'));
    }

    foreach ($services as $service) {
      if (!$service->getIsLocked()) {
        throw new PhutilArgumentUsageException(
          pht(
            'Service "%s" is not locked!',
            $service->getName()));
      }
    }

    foreach ($services as $service) {
      $this->updateServiceLock($service, false);

      $console->writeOut(
        "**<bg:green> %s </bg>** %s\n",
        pht('UNLOCKED'),
        pht('Service "%s" was unlocked.', $service->getName()));
    }

    return 0;
  }

}
