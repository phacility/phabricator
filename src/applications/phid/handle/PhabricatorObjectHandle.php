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

class PhabricatorObjectHandle {

  private $uri;
  private $phid;
  private $type;
  private $name;
  private $email;
  private $fullName;
  private $imageURI;
  private $timestamp;
  private $alternateID;

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function getURI() {
    return $this->uri;
  }

  public function setPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  public function getPHID() {
    return $this->phid;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setFullName($full_name) {
    $this->fullName = $full_name;
    return $this;
  }

  public function getFullName() {
    if ($this->fullName !== null) {
      return $this->fullName;
    }
    return $this->getName();
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setEmail($email) {
    $this->email = $email;
    return $this;
  }

  public function getEmail() {
    return $this->email;
  }

  public function setImageURI($uri) {
    $this->imageURI = $uri;
    return $this;
  }

  public function getImageURI() {
    return $this->imageURI;
  }

  public function setTimestamp($timestamp) {
    $this->timestamp = $timestamp;
    return $this;
  }

  public function getTimestamp() {
    return $this->timestamp;
  }

  public function setAlternateID($alternate_id) {
    $this->alternateID = $alternate_id;
    return $this;
  }

  public function getAlternateID() {
    return $this->alternateID;
  }

  public function getTypeName() {
    static $map = array(
      PhabricatorPHIDConstants::PHID_TYPE_USER => 'User',
      PhabricatorPHIDConstants::PHID_TYPE_TASK => 'Task',
      PhabricatorPHIDConstants::PHID_TYPE_DREV => 'Revision',
      PhabricatorPHIDConstants::PHID_TYPE_CMIT => 'Commit',
    );

    return idx($map, $this->getType());
  }

  public function renderLink() {

    switch ($this->getType()) {
      case PhabricatorPHIDConstants::PHID_TYPE_USER:
        $name = $this->getName();
        break;
      default:
        $name = $this->getFullName();
    }

    return phutil_render_tag(
      'a',
      array(
        'href' => $this->getURI(),
      ),
      phutil_escape_html($name));
  }

}
