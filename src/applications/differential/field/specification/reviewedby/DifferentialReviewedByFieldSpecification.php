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

final class DifferentialReviewedByFieldSpecification
  extends DifferentialFieldSpecification {

  private $reviewedBy;

  protected function didSetRevision() {
    $this->reviewedBy = array();
    $revision = $this->getRevision();
    $reviewer = $revision->loadReviewedBy();

    if ($reviewer) {
      $this->reviewedBy = array($reviewer);
    }
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function getCommitMessageKey() {
    return 'reviewedByPHIDs';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->reviewedBy = $value;
    return $this;
  }

  public function shouldAppearOnCommitMessageTemplate() {
    return false;
  }

  public function renderLabelForCommitMessage() {
    return 'Reviewed By';
  }

  public function getRequiredHandlePHIDsForCommitMessage() {
    return $this->reviewedBy;
  }

  public function renderValueForCommitMessage($is_edit) {
    if ($is_edit) {
      return null;
    }

    if (!$this->reviewedBy) {
      return null;
    }

    $names = array();
    foreach ($this->reviewedBy as $phid) {
      $names[] = $this->getHandle($phid)->getName();
    }

    return implode(', ', $names);
  }

  public function parseValueFromCommitMessage($value) {
    return $this->parseCommitMessageUserList($value);
  }

}
