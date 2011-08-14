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

}
