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

/**
 * @group maniphest
 */
class ManiphestTask extends ManiphestDAO {

  protected $phid;
  protected $authorPHID;
  protected $ownerPHID;
  protected $ccPHIDs = array();

  protected $status;
  protected $priority;

  protected $title;
  protected $description;
  protected $originalEmailSource;
  protected $mailKey;

  protected $attached = array();
  protected $projectPHIDs = array();
  private $projectsNeedUpdate;
  private $subscribersNeedUpdate;

  protected $ownerOrdering;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'ccPHIDs' => self::SERIALIZATION_JSON,
        'attached' => self::SERIALIZATION_JSON,
        'projectPHIDs' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function getAttachedPHIDs($type) {
    return array_keys(idx($this->attached, $type, array()));
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_TASK);
  }

  public function getCCPHIDs() {
    return array_values(nonempty($this->ccPHIDs, array()));
  }

  public function setProjectPHIDs(array $phids) {
    $this->projectPHIDs = array_values($phids);
    $this->projectsNeedUpdate = true;
    return $this;
  }

  public function getProjectPHIDs() {
    return array_values(nonempty($this->projectPHIDs, array()));
  }

  public function setCCPHIDs(array $phids) {
    $this->ccPHIDs = array_values($phids);
    $this->subscribersNeedUpdate = true;
    return $this;
  }

  public function setOwnerPHID($phid) {
    $this->ownerPHID = $phid;
    $this->subscribersNeedUpdate = true;
    return $this;
  }

  public function setAuxiliaryAttribute($key, $val) {
    $this->removeAuxiliaryAttribute($key);

    $attribute = new ManiphestTaskAuxiliaryStorage();
    $attribute->setTaskPHID($this->phid);
    $attribute->setName($key);
    $attribute->setValue($val);
    $attribute->save();
  }

  public function loadAuxiliaryAttribute($key) {
    $attribute = id(new ManiphestTaskAuxiliaryStorage())->loadOneWhere(
      'taskPHID = %s AND name = %s',
      $this->getPHID(),
      $key);

    return $attribute;
  }

  public function removeAuxiliaryAttribute($key) {
    $attribute = id(new ManiphestTaskAuxiliaryStorage())->loadOneWhere(
      'taskPHID = %s AND name = %s',
      $this->getPHID(),
      $key);

    if ($attribute) {
      $attribute->delete();
    }
  }

  public function loadAuxiliaryAttributes() {
    $attributes = id(new ManiphestTaskAuxiliaryStorage())->loadAllWhere(
      'taskPHID = %s',
      $this->getPHID());

    return $attributes;
  }

  public function save() {
    if (!$this->mailKey) {
      $this->mailKey = Filesystem::readRandomCharacters(20);
    }

    $result = parent::save();

    if ($this->projectsNeedUpdate) {
      // If we've changed the project PHIDs for this task, update the link
      // table.
      ManiphestTaskProject::updateTaskProjects($this);
      $this->projectsNeedUpdate = false;
    }

    if ($this->subscribersNeedUpdate) {
      // If we've changed the subscriber PHIDs for this task, update the link
      // table.
      ManiphestTaskSubscriber::updateTaskSubscribers($this);
      $this->subscribersNeedUpdate = false;
    }

    return $result;
  }

}
