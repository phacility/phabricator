<?php

final class PhabricatorEventEngine {

  public static function initialize() {
    // NOTE: If any of this fails, we just log it and move on. It's important
    // to try to make it through here because users may have difficulty fixing
    // fix the errors if we don't: for example, if we fatal here a user may not
    // be able to run `bin/config` in order to remove an invalid listener.

    // Load automatic listeners.
    $listeners = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorAutoEventListener')
      ->loadObjects();

    // Load configured listeners.
    $config_listeners = PhabricatorEnv::getEnvConfig('events.listeners');
    foreach ($config_listeners as $listener_class) {
      try {
        $listeners[] = newv($listener_class, array());
      } catch (Exception $ex) {
        phlog($ex);
      }
    }

    // Add built-in listeners.
    $listeners[] = new DarkConsoleEventPluginAPI();

    // Add application listeners.
    $applications = PhabricatorApplication::getAllInstalledApplications();
    foreach ($applications as $application) {
      $app_listeners = $application->getEventListeners();
      foreach ($app_listeners as $listener) {
        $listener->setApplication($application);
        $listeners[] = $listener;
      }
    }

    // Now, register all of the listeners.
    foreach ($listeners as $listener) {
      try {
        $listener->register();
      } catch (Exception $ex) {
        phlog($ex);
      }
    }
  }

}
