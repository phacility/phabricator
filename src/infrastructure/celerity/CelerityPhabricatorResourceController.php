<?php

/**
 * Delivers CSS and JS resources to the browser. This controller handles all
 * ##/res/## requests, and manages caching, package construction, and resource
 * preprocessing.
 *
 * @group celerity
 */
final class CelerityPhabricatorResourceController
  extends CelerityResourceController {

  private $path;
  private $hash;

  protected function getRootDirectory() {
    $root = dirname(phutil_get_library_root('phabricator'));
    return $root.'/webroot/';
  }

  public function willProcessRequest(array $data) {
    $this->path = $data['path'];
    $this->hash = $data['hash'];
  }

  public function processRequest() {
    return $this->serveResource($this->path);
  }

  protected function buildResourceTransformer() {
    $xformer = new CelerityResourceTransformer();
    $xformer->setMinify(
      !PhabricatorEnv::getEnvConfig('phabricator.developer-mode') &&
      PhabricatorEnv::getEnvConfig('celerity.minify'));
    $xformer->setCelerityMap(CelerityResourceMap::getInstance());
    return $xformer;
  }

}
