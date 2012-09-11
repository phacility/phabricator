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

final class DifferentialTitleFieldSpecification
  extends DifferentialFreeformFieldSpecification {

  private $title;
  private $error = true;

  public function shouldAppearOnEdit() {
    return true;
  }

  protected function didSetRevision() {
    $this->title = $this->getRevision()->getTitle();
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $this->title = $request->getStr('title');
    $this->error = null;
    return $this;
  }

  public function renderEditControl() {
    return id(new AphrontFormTextAreaControl())
      ->setLabel('Title')
      ->setName('title')
      ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
      ->setError($this->error)
      ->setValue($this->title);
  }

  public function willWriteRevision(DifferentialRevisionEditor $editor) {
    $this->getRevision()->setTitle($this->title);
  }

  public function validateField() {
    if (!strlen($this->title)) {
      $this->error = 'Required';
      throw new DifferentialFieldValidationException(
        "You must provide a title.");
    }
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function getCommitMessageKey() {
    return 'title';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->title = $value;
    return $this;
  }

  public function shouldOverwriteWhenCommitMessageIsEdited() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return 'Title';
  }

  public function renderValueForCommitMessage($is_edit) {
    return $this->title;
  }

  public function parseValueFromCommitMessage($value) {
    return preg_replace('/\s*\n\s*/', ' ', $value);
  }

  public function shouldAppearOnRevisionList() {
    return true;
  }

  public function renderHeaderForRevisionList() {
    return 'Revision';
  }

  public function getColumnClassForRevisionList() {
    return 'wide pri';
  }

  public function renderValueForRevisionList(DifferentialRevision $revision) {
    return phutil_render_tag(
      'a',
      array(
        'href' => '/D'.$revision->getID(),
      ),
      phutil_escape_html($revision->getTitle()));
  }

}
