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
  private $package;

  protected function getRootDirectory() {
    $root = dirname(phutil_get_library_root('phabricator'));
    return $root.'/webroot/';
  }

  public function willProcessRequest(array $data) {
    $this->path = $data['path'];
    $this->hash = $data['hash'];
    $this->package = !empty($data['package']);
  }

  public function processRequest() {
    $package_hash = null;
    if ($this->package) {
      $package_hash = $this->hash;
    }
    return $this->serveResource($this->path, $package_hash);
  }

  protected function buildResourceTransformer() {
    $xformer = new CelerityResourceTransformer();
    $xformer->setMinify(PhabricatorEnv::getEnvConfig('celerity.minify'));
    $xformer->setCelerityMap(CelerityResourceMap::getInstance());
    return $xformer;
  }

}
