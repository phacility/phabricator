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
abstract class PhameBlogSkin extends PhabricatorController {

  private $blog;
  private $baseURI;
  private $preview;
  private $specification;

  public function setSpecification(PhameSkinSpecification $specification) {
    $this->specification = $specification;
    return $this;
  }

  public function getSpecification() {
    return $this->specification;
  }

  public function setPreview($preview) {
    $this->preview = $preview;
    return $this;
  }

  public function getPreview() {
    return $this->preview;
  }

  final public function setBaseURI($base_uri) {
    $this->baseURI = $base_uri;
    return $this;
  }

  final public function getURI($path) {
    return $this->baseURI.$path;
  }

  final public function setBlog(PhameBlog $blog) {
    $this->blog = $blog;
    return $this;
  }

  final public function getBlog() {
    return $this->blog;
  }

}
