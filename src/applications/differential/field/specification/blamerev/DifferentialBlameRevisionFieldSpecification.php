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

final class DifferentialBlameRevisionFieldSpecification
  extends DifferentialFieldSpecification {

  private $value;

  public function getStorageKey() {
    return 'phabricator:blame-revision';
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
    return id(new AphrontFormTextControl())
      ->setLabel('Blame Revision')
      ->setCaption('Revision which broke the stuff which this change fixes.')
      ->setName($this->getStorageKey())
      ->setValue($this->value);
  }

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return 'Blame Revision:';
  }

  public function renderValueForRevisionView() {
    if (!$this->value) {
      return null;
    }
    $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();
    return $engine->markupText($this->value);
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
    return 'blameRevision';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->value = $value;
    return $this;
  }

  public function shouldOverwriteWhenCommitMessageIsEdited() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return 'Blame Revision';
  }

  public function renderValueForCommitMessage($is_edit) {
    return $this->value;
  }

  public function getSupportedCommitMessageLabels() {
    return array(
      'Blame Revision',
      'Blame Rev',
    );
  }

  public function parseValueFromCommitMessage($value) {
    return $value;
  }

}
