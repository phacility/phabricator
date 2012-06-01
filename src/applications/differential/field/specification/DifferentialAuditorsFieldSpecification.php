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

final class DifferentialAuditorsFieldSpecification
  extends DifferentialFieldSpecification {

  private $auditors = array();

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function shouldAppearOnCommitMessageTemplate() {
    return false;
  }

  public function getCommitMessageKey() {
    return 'auditorPHIDs';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->auditors = nonempty($value, array());
    return $this;
  }

  public function renderLabelForCommitMessage() {
    return 'Auditors';
  }

  public function getRequiredHandlePHIDsForCommitMessage() {
    return $this->auditors;
  }

  public function renderValueForCommitMessage($is_edit) {
    if (!$this->auditors) {
      return null;
    }

    $names = array();
    foreach ($this->auditors as $phid) {
      $names[] = $this->getHandle($phid)->getName();
    }

    return implode(', ', $names);
  }

  public function parseValueFromCommitMessage($value) {
    return $this->parseCommitMessageUserList($value);
  }

  public function getStorageKey() {
    return 'phabricator:auditors';
  }

  public function getValueForStorage() {
    return json_encode($this->auditors);
  }

  public function setValueFromStorage($value) {
    $auditors = json_decode($value, true);
    if (!is_array($auditors)) {
      $auditors = array();
    }
    $this->auditors = $auditors;
    return $this;
  }

}
