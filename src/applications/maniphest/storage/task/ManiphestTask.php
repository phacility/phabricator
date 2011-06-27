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

class ManiphestTask extends ManiphestDAO {

  protected $phid;
  protected $authorPHID;
  protected $ownerPHID;
  protected $ccPHIDs = array();

  protected $status;
  protected $priority;

  protected $title;
  protected $description;

  protected $mailKey;

  protected $attached = array();
  protected $projectPHIDs = array();

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
    return nonempty($this->ccPHIDs, array());
  }

  public function save() {
    if (!$this->mailKey) {
      $this->mailKey = sha1(Filesystem::readRandomBytes(20));
    }
    return parent::save();
  }

}
