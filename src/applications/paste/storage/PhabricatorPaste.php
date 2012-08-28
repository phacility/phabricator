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

final class PhabricatorPaste extends PhabricatorPasteDAO
  implements PhabricatorPolicyInterface {

  protected $phid;
  protected $title;
  protected $authorPHID;
  protected $filePHID;
  protected $language;
  protected $parentPHID;

  private $content;

  public function getURI() {
    return '/P'.$this->getID();
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_PSTE);
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    if ($capability == PhabricatorPolicyCapability::CAN_VIEW) {
      return PhabricatorPolicies::POLICY_USER;
    }
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    return ($user->getPHID() == $this->getAuthorPHID());
  }

  public function getFullName() {
    $title = $this->getTitle();
    if (!$title) {
      $title = '(An Untitled Masterwork)';
    }
    return 'P'.$this->getID().' '.$title;
  }

  public function getContent() {
    if ($this->content === null) {
      throw new Exception("Call attachContent() before getContent()!");
    }
    return $this->content;
  }

  public function attachContent($content) {
    $this->content = $content;
    return $this;
  }

}
