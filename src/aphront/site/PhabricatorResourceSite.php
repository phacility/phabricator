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

    // These are CDN routes, so we let them through even if the "Host" header
    // doesn't match anything we recognize. The
    $whitelist = array(
      '/res/',
      '/file/data/',
      '/file/xform/',
    );

    $path = $request->getPath();
    if ($this->isPathPrefixMatch($path, $whitelist)) {
      return new PhabricatorResourceSite();
    }

    return null;
  }

}
