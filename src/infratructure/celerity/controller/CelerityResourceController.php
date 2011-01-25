<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class CelerityResourceController extends AphrontController {
  
  private $path;
  private $hash;
  
  public function willProcessRequest(array $data) {
    $this->path = $data['path'];
    $this->hash = $data['hash'];
  }

  public function processRequest() {
    $path = $this->path;

    // Sanity checking to keep this from exposing anything sensitive.
    $path = preg_replace('@(//|\\.\\.)@', '', $path);
    $matches = null;
    if (!preg_match('/\.(css|js)$/', $path, $matches)) {
      throw new Exception("Only CSS and JS resources may be served.");
    }

    $type = $matches[1];


    $root = dirname(phutil_get_library_root('phabricator'));

    try {
      $data = Filesystem::readFile($root.'/webroot/'.$path);
    } catch (Exception $ex) {
      return new Aphront404Response();
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

    return $response;
  }

}
