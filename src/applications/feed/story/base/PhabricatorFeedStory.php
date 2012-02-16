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

abstract class PhabricatorFeedStory {

  private $data;

  private $handles;
  private $objects;
  private $framed;

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

  final public function setFramed($framed) {
    $this->framed = $framed;
    return $this;
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

  final public function getStoryData() {
    return $this->data;
  }

  final public function getEpoch() {
    return $this->getStoryData()->getEpoch();
  }

  final public function getChronologicalKey() {
    return $this->getStoryData()->getChronologicalKey();
  }

  final protected function renderHandleList(array $phids) {
    $list = array();
    foreach ($phids as $phid) {
      $list[] = $this->linkTo($phid);
    }
    return implode(', ', $list);
  }

  final protected function linkTo($phid) {
    $handle = $this->getHandle($phid);

    // NOTE: We render our own link here to customize the styling and add
    // the '_top' target for framed feeds.

    return phutil_render_tag(
      'a',
      array(
        'href'    => $handle->getURI(),
        'target'  => $this->framed ? '_top' : null,
      ),
      phutil_escape_html($handle->getLinkName()));
  }

  final protected function renderString($str) {
    return '<strong>'.phutil_escape_html($str).'</strong>';
  }

  final protected function renderSummary($text, $len = 128) {
    if ($len) {
      $text = phutil_utf8_shorten($text, $len);
    }
    $text = phutil_escape_html($text);
    $text = str_replace("\n", '<br />', $text);
    return $text;
  }

}
