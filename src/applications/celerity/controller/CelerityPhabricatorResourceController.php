<?php

/**
 * Delivers CSS and JS resources to the browser. This controller handles all
 * `/res/` requests, and manages caching, package construction, and resource
 * preprocessing.
 */
final class CelerityPhabricatorResourceController
  extends CelerityResourceController {

  private $path;
  private $hash;
  private $library;
  private $postprocessorKey;

  public function getCelerityResourceMap() {
    return CelerityResourceMap::getNamedInstance($this->library);
  }

  public function handleRequest(AphrontRequest $request) {
    $this->path = $request->getURIData('path');
    $this->hash = $request->getURIData('hash');
    $this->library = $request->getURIData('library');
    $this->postprocessorKey = $request->getURIData('postprocessor');

    // Check that the resource library exists before trying to serve resources
    // from it.
    try {
      $this->getCelerityResourceMap();
    } catch (Exception $ex) {
      return new Aphront400Response();
    }

    return $this->serveResource($this->path);
  }

  protected function buildResourceTransformer() {
    $minify_on = PhabricatorEnv::getEnvConfig('celerity.minify');
    $developer_on = PhabricatorEnv::getEnvConfig('phabricator.developer-mode');

    $should_minify = ($minify_on && !$developer_on);

    return id(new CelerityResourceTransformer())
      ->setMinify($should_minify)
      ->setPostprocessorKey($this->postprocessorKey)
      ->setCelerityMap($this->getCelerityResourceMap());
  }

  protected function getCacheKey($path) {
    return parent::getCacheKey($path.';'.$this->postprocessorKey);
  }

}
