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

final class DifferentialCCsFieldSpecification
  extends DifferentialFieldSpecification {

  private $ccs = array();

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function getRequiredHandlePHIDsForRevisionView() {
    return $this->getCCPHIDs();
  }

  public function renderLabelForRevisionView() {
    return 'CCs:';
  }

  public function renderValueForRevisionView() {
    $cc_phids = $this->getCCPHIDs();
    if (!$cc_phids) {
      return '<em>None</em>';
    }

    $links = array();
    foreach ($cc_phids as $cc_phid) {
      $links[] = $this->getHandle($cc_phid)->renderLink();
    }

    return implode(', ', $links);
  }

  private function getCCPHIDs() {
    $revision = $this->getRevision();
    return $revision->getCCPHIDs();
  }

  public function shouldAppearOnEdit() {
    $this->ccs = $this->getCCPHIDs();
    return true;
  }

  public function getRequiredHandlePHIDsForRevisionEdit() {
    return $this->ccs;
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $this->ccs = $request->getArr('cc');
    return $this;
  }

  public function renderEditControl() {
    $cc_map = array();
    foreach ($this->ccs as $phid) {
      $cc_map[$phid] = $this->getHandle($phid)->getFullName();
    }
    return id(new AphrontFormTokenizerControl())
      ->setLabel('CC')
      ->setName('cc')
      ->setDatasource('/typeahead/common/mailable/')
      ->setValue($cc_map);
  }

  public function willWriteRevision(DifferentialRevisionEditor $editor) {
    $editor->setCCPHIDs($this->ccs);
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function getCommitMessageKey() {
    return 'ccPHIDs';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->value = nonempty($value, array());
    return $this;
  }

}
