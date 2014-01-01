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

  public function getCelerityResourceMap() {
    return CelerityResourceMap::getNamedInstance('phabricator');
  }

  public function willProcessRequest(array $data) {
    $this->path = $data['path'];
    $this->hash = $data['hash'];
  }

  public function processRequest() {
    return $this->serveResource($this->path);
  }

  protected function buildResourceTransformer() {
    $minify_on = PhabricatorEnv::getEnvConfig('celerity.minify');
    $developer_on = PhabricatorEnv::getEnvConfig('phabricator.developer-mode');

    $should_minify = ($minify_on && !$developer_on);

    return id(new CelerityResourceTransformer())
      ->setMinify($should_minify)
      ->setCelerityMap($this->getCelerityResourceMap());
  }

}
