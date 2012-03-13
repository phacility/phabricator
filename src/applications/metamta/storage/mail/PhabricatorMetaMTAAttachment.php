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

final class PhabricatorMetaMTAAttachment {
  protected $data;
  protected $filename;
  protected $mimetype;

  public function __construct($data, $filename, $mimetype) {
    $this->setData($data);
    $this->setFilename($filename);
    $this->setMimeType($mimetype);
  }

  public function getData() {
    return $this->data;
  }

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  public function getFilename() {
    return $this->filename;
  }

  public function setFilename($filename) {
    $this->filename = $filename;
    return $this;
  }

  public function getMimeType() {
    return $this->mimetype;
  }

  public function setMimeType($mimetype) {
    $this->mimetype = $mimetype;
    return $this;
  }
}
