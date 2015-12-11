<?php

final class PhabricatorResourceSite extends PhabricatorSite {

  public function getDescription() {
    return pht('Serves static resources like images, CSS and JS.');
  }

  public function getPriority() {
    return 2000;
  }

  public function newSiteForRequest(AphrontRequest $request) {
    $host = $request->getHost();

    $uri = PhabricatorEnv::getEnvConfig('security.alternate-file-domain');
    if (!strlen($uri)) {
      return null;
    }

    if ($this->isHostMatch($host, array($uri))) {
      return new PhabricatorResourceSite();
    }

    return null;
  }

  public function getRoutingMaps() {
    $applications = PhabricatorApplication::getAllInstalledApplications();

    $maps = array();
    foreach ($applications as $application) {
      $maps[] = $this->newRoutingMap()
        ->setApplication($application)
        ->setRoutes($application->getResourceRoutes());
    }

    return $maps;
  }

}
