<?php

final class PhabricatorShortSite extends PhabricatorSite {

  public function getDescription() {
    return pht('Serves shortened URLs.');
  }

  public function getPriority() {
    return 2500;
  }

  public function newSiteForRequest(AphrontRequest $request) {
    $host = $request->getHost();

    $uri = PhabricatorEnv::getEnvConfig('phurl.short-uri');
    if (!strlen($uri)) {
      return null;
    }

    $phurl_installed = PhabricatorApplication::isClassInstalled(
      'PhabricatorPhurlApplication');
    if (!$phurl_installed) {
      return false;
    }

    if ($this->isHostMatch($host, array($uri))) {
      return new PhabricatorShortSite();
    }

    return null;
  }

  public function getRoutingMaps() {
    $app = PhabricatorApplication::getByClass('PhabricatorPhurlApplication');

    $maps = array();
    $maps[] = $this->newRoutingMap()
      ->setApplication($app)
      ->setRoutes($app->getShortRoutes());
    return $maps;
  }

}
