<?php

/**
 * @group events
 */
final class PhabricatorEventEngine {

  public static function initialize() {
    $listeners = PhabricatorEnv::getEnvConfig('events.listeners');
    foreach ($listeners as $listener) {
      try {
        id(new $listener())->register();
      } catch (Exception $ex) {
        // If the listener does not exist, or throws when registering, just
        // log it and continue. In particular, this is important to let you
        // run `bin/config` in order to remove an invalid listener.
        phlog($ex);
      }
    }

    // Register the DarkConosole event logger.
    id(new DarkConsoleEventPluginAPI())->register();
    id(new ManiphestEdgeEventListener())->register();

    $applications = PhabricatorApplication::getAllInstalledApplications();
    foreach ($applications as $application) {
      $listeners = $application->getEventListeners();
      foreach ($listeners as $listener) {
        $listener->setApplication($application);
        $listener->register();
      }
    }

  }

}
