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

abstract class CelerityResourceController extends PhabricatorController {

  abstract protected function getRootDirectory();

  protected function buildResourceTransformer() {
    return null;
  }

  public function shouldRequireLogin() {
    return false;
  }

  public function shouldRequireEnabledUser() {
    return false;
  }

  private function getDiskPath($to_resource = null) {
    return $this->getRootDirectory().$to_resource;
  }

  protected function serveResource($path, $package_hash = null) {
    // Sanity checking to keep this from exposing anything sensitive, since it
    // ultimately boils down to disk reads.
    if (preg_match('@(//|\.\.)@', $path)) {
      return new Aphront400Response();
    }

    $type = CelerityResourceTransformer::getResourceType($path);
    $type_map = $this->getSupportedResourceTypes();

    if (empty($type_map[$type])) {
      throw new Exception("Only static resources may be served.");
    }

    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
        !PhabricatorEnv::getEnvConfig('celerity.force-disk-reads')) {
      // Return a "304 Not Modified". We don't care about the value of this
      // field since we never change what resource is served by a given URI.
      return $this->makeResponseCacheable(new Aphront304Response());
    }

    if ($package_hash) {
      $map = CelerityResourceMap::getInstance();
      $paths = $map->resolvePackage($package_hash);
      if (!$paths) {
        return new Aphront404Response();
      }

      try {
        $data = array();
        foreach ($paths as $package_path) {
          $disk_path = $this->getDiskPath($package_path);
          $data[] = Filesystem::readFile($disk_path);
        }
        $data = implode("\n\n", $data);
      } catch (Exception $ex) {
        return new Aphront404Response();
      }
    } else {
      try {
        $disk_path = $this->getDiskPath($path);
        $data = Filesystem::readFile($disk_path);
      } catch (Exception $ex) {
        return new Aphront404Response();
      }
    }

    $xformer = $this->buildResourceTransformer();
    if ($xformer) {
      $data = $xformer->transformResource($path, $data);
    }

    $response = new AphrontFileResponse();
    $response->setContent($data);
    $response->setMimeType($type_map[$type]);
    return $this->makeResponseCacheable($response);
  }

  protected function getSupportedResourceTypes() {
    return array(
      'css' => 'text/css; charset=utf-8',
      'js'  => 'text/javascript; charset=utf-8',
      'png' => 'image/png',
      'gif' => 'image/gif',
      'jpg' => 'image/jpg',
      'swf' => 'application/x-shockwave-flash',
    );
  }

  private function makeResponseCacheable(AphrontResponse $response) {
    $response->setCacheDurationInSeconds(60 * 60 * 24 * 30);
    $response->setLastModified(time());

    return $response;
  }

}
