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

final class DifferentialSummaryFieldSpecification
  extends DifferentialFieldSpecification {

  private $summary;

  public function shouldAppearOnEdit() {
    $this->summary = $this->getRevision()->getSummary();
    return true;
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

}
