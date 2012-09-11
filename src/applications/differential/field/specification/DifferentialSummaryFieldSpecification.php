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

final class DifferentialSummaryFieldSpecification
  extends DifferentialFreeformFieldSpecification {

  private $summary = '';

  public function shouldAppearOnEdit() {
    return true;
  }

  protected function didSetRevision() {
    $this->summary = (string)$this->getRevision()->getSummary();
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $this->summary = $request->getStr('summary');
    return $this;
  }

  public function renderEditControl() {
    return id(new AphrontFormTextAreaControl())
      ->setLabel('Summary')
      ->setName('summary')
      ->setValue($this->summary);
  }

  public function willWriteRevision(DifferentialRevisionEditor $editor) {
    $this->getRevision()->setSummary($this->summary);
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function getCommitMessageKey() {
    return 'summary';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->summary = (string)$value;
    return $this;
  }

  public function shouldOverwriteWhenCommitMessageIsEdited() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return 'Summary';
  }

  public function renderValueForCommitMessage($is_edit) {
    return $this->summary;
  }

  public function parseValueFromCommitMessage($value) {
    return (string)$value;
  }

  public function renderValueForMail($phase) {
    if ($phase != DifferentialMailPhase::WELCOME) {
      return null;
    }

    if ($this->summary == '') {
      return null;
    }

    return $this->summary;
  }

}
