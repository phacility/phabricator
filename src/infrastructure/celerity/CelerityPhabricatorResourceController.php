<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
