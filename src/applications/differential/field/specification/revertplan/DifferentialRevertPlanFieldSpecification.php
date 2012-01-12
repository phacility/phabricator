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

final class DifferentialRevertPlanFieldSpecification
  extends DifferentialFieldSpecification {

  private $value;

  public function getStorageKey() {
    return 'phabricator:revert-plan';
  }

  public function getValueForStorage() {
    return $this->value;
  }

  public function setValueFromStorage($value) {
    $this->value = $value;
    return $this;
  }

  public function shouldAppearOnEdit() {
    return true;
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $this->value = $request->getStr($this->getStorageKey());
    return $this;
  }

  public function renderEditControl() {
    return id(new AphrontFormTextAreaControl())
      ->setLabel('Revert Plan')
      ->setName($this->getStorageKey())
      ->setCaption('Special steps required to safely revert this change.')
      ->setValue($this->value);
  }

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return 'Revert Plan:';
  }

  public function renderValueForRevisionView() {
    if (!$this->value) {
      return null;
    }
    return phutil_escape_html($this->value);
  }

  public function shouldAppearOnConduitView() {
    return true;
  }

  public function getValueForConduit() {
    return $this->value;
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function getCommitMessageKey() {
    return 'revertPlan';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->value = $value;
    return $this;
  }

  public function shouldOverwriteWhenCommitMessageIsEdited() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return 'Revert Plan';
  }


  public function renderValueForCommitMessage($is_edit) {
    return $this->value;
  }

  public function getSupportedCommitMessageLabels() {
    return array(
      'Revert Plan',
      'Revert',
    );
  }

  public function parseValueFromCommitMessage($value) {
    return $value;
  }

}
