<?php

final class PhameBlogResourceSite extends PhameSite {

  public function getDescription() {
    return pht('Serves static resources for blogs.');
  }

  public function getPriority() {
    return 3000;
  }

  public function newSiteForRequest(AphrontRequest $request) {
    if (!$this->isPhameActive()) {
      return null;
    }

    $whitelist = array(
      '/phame/r/',
    );

    $path = $request->getPath();
    if (!$this->isPathPrefixMatch($path, $whitelist)) {
      return null;
    }

    return new PhameBlogResourceSite();
  }

}
