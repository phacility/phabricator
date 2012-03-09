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
final class CelerityResourceController extends AphrontController {

  private $path;
  private $hash;
  private $package;

  public function willProcessRequest(array $data) {
    $this->path = $data['path'];
    $this->hash = $data['hash'];
    $this->package = !empty($data['package']);
  }

  public function processRequest() {
    $path = $this->path;

    // Sanity checking to keep this from exposing anything sensitive.
    $path = preg_replace('@(//|\\.\\.)@', '', $path);
    $matches = null;
    if (!preg_match('/\.(css|js)$/', $path, $matches)) {
      throw new Exception("Only CSS and JS resources may be served.");
    }

    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
        !PhabricatorEnv::getEnvConfig('celerity.force-disk-reads')) {
      // Return a "304 Not Modified". We don't care about the value of this
      // field since we never change what resource is served by a given URI.
      return $this->makeResponseCacheable(new Aphront304Response());
    }

    $type = $matches[1];

    $root = dirname(phutil_get_library_root('phabricator'));

    if ($this->package) {
      $map = CelerityResourceMap::getInstance();
      $paths = $map->resolvePackage($this->hash);
      if (!$paths) {
        return new Aphront404Response();
      }

      try {
        $data = array();
        foreach ($paths as $path) {
          $data[] = Filesystem::readFile($root.'/webroot/'.$path);
        }
        $data = implode("\n\n", $data);
      } catch (Exception $ex) {
        return new Aphront404Response();
      }
    } else {
      try {
        $data = Filesystem::readFile($root.'/webroot/'.$path);
      } catch (Exception $ex) {
        return new Aphront404Response();
      }
    }

    $response = new AphrontFileResponse();
    $response->setContent($data);
    switch ($type) {
      case 'css':
        $response->setMimeType("text/css; charset=utf-8");
        break;
      case 'js':
        $response->setMimeType("text/javascript; charset=utf-8");
        break;
    }

    return $this->makeResponseCacheable($response);
  }

  private function makeResponseCacheable(AphrontResponse $response) {
    $response->setCacheDurationInSeconds(60 * 60 * 24 * 30);
    $response->setLastModified(time());

    return $response;
  }

}
