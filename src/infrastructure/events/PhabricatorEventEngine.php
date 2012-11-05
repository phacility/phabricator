<?php

/**
 * @group events
 */
final class PhabricatorEventEngine {

  public static function initialize() {
    $listeners = PhabricatorEnv::getEnvConfig('events.listeners');
    foreach ($listeners as $listener) {
      id(new $listener())->register();
    }

    // Register the DarkConosole event logger.
    id(new DarkConsoleEventPluginAPI())->register();
    id(new ManiphestEdgeEventListener())->register();

    $applications = PhabricatorApplication::getAllInstalledApplications();
    foreach ($applications as $application) {
      $listeners = $application->getEventListeners();
      foreach ($listeners as $listener) {
        $listener->register();
      }
    }

  }

}
