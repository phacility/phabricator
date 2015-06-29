<?php

final class PhabricatorPlatformSite extends PhabricatorSite {

  public function getDescription() {
    return pht('Serves the core platform and applications.');
  }

  public function getPriority() {
    return 1000;
  }

  public function newSiteForRequest(AphrontRequest $request) {
    $uris = array();
    $uris[] = PhabricatorEnv::getEnvConfig('phabricator.base-uri');
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

}
