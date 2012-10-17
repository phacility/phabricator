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
 * @group phame
 */
final class PhameResourceController extends CelerityResourceController {

  private $id;
  private $hash;
  private $name;
  private $root;

  protected function getRootDirectory() {
    return $this->root;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->hash = $data['hash'];
    $this->name = $data['name'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    // We require a visible blog associated with a given skin to serve
    // resources, so you can't go fishing around where you shouldn't be.

    $blog = id(new PhameBlogQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }

    $skin = $blog->getSkinRenderer($request);
    $spec = $skin->getSpecification();

    $this->root = $spec->getRootDirectory().DIRECTORY_SEPARATOR;
    return $this->serveResource($this->name, $package_hash = null);
  }

  protected function buildResourceTransformer() {
    $xformer = new CelerityResourceTransformer();
    $xformer->setMinify(false);
    $xformer->setTranslateURICallback(array($this, 'translateResourceURI'));
    return $xformer;
  }

  public function translateResourceURI(array $matches) {
    $uri = trim($matches[1], "'\" \r\t\n");

    if (Filesystem::pathExists($this->root.$uri)) {
      $hash = filemtime($this->root.$uri);
    } else {
      $hash = '-';
    }

    $uri = '/phame/r/'.$this->id.'/'.$hash.'/'.$uri;
    return 'url('.$uri.')';
  }

}
