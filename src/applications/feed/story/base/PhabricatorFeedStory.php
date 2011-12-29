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

abstract class PhabricatorFeedStory {

  private $data;

  private $handles;
  private $objects;

  final public function __construct(PhabricatorFeedStoryData $data) {
    $this->data = $data;
  }

  abstract public function renderView();

  public function getRequiredHandlePHIDs() {
    return array();
  }

  public function getRequiredObjectPHIDs() {
    return array();
  }

  final public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  final public function setObjects(array $objects) {
    $this->objects = $objects;
    return $this;
  }

  final protected function getHandles() {
    return $this->handles;
  }

  final protected function getHandle($phid) {
    if (isset($this->handles[$phid])) {
      if ($this->handles[$phid] instanceof PhabricatorObjectHandle) {
        return $this->handles[$phid];
      }
    }

    $handle = new PhabricatorObjectHandle();
    $handle->setPHID($phid);
    $handle->setName("Unloaded Object '{$phid}'");

    return $handle;
  }

  final protected function getObjects() {
    return $this->objects;
  }

  final protected function getStoryData() {
    return $this->data;
  }

  final public function getEpoch() {
    return $this->getStoryData()->getEpoch();
  }

}
