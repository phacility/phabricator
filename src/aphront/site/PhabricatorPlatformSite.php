<?php

final class PhabricatorPlatformSite extends PhabricatorSite {

  public function getDescription() {
    return pht('Serves the core platform and applications.');
  }

  public function getPriority() {
    return 1000;
  }

  public function newSiteForRequest(AphrontRequest $request) {
    // If no base URI has been configured yet, match this site so the user
    // can follow setup instructions.
    $base_uri = PhabricatorEnv::getEnvConfig('phabricator.base-uri');
    if (!strlen($base_uri)) {
      return new PhabricatorPlatformSite();
    }

    $uris = array();
    $uris[] = $base_uri;
    $uris[] = PhabricatorEnv::getEnvConfig('phabricator.production-uri');

    $allowed = PhabricatorEnv::getEnvConfig('phabricator.allowed-uris');
    if ($allowed) {
      foreach ($allowed as $uri) {
        $uris[] = $uri;
      }
    }

    $host = $request->getHost();
    if ($this->isHostMatch($host, $uris)) {
      return new PhabricatorPlatformSite();
    }

    return null;
  }

  public function getRoutingMaps() {
    $applications = PhabricatorApplication::getAllInstalledApplications();

    $maps = array();
    foreach ($applications as $application) {
      $maps[] = $this->newRoutingMap()
        ->setApplication($application)
        ->setRoutes($application->getRoutes());
    }

    return $maps;
  }

  public function new404Controller(AphrontRequest $request) {
    return new PhabricatorPlatform404Controller();
  }

}
