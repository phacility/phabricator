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

final class DifferentialArcanistProjectFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function getRequiredHandlePHIDs() {
    $arcanist_phid = $this->getArcanistProjectPHID();
    if (!$arcanist_phid) {
      return array();
    }

    return array($arcanist_phid);
  }

  public function renderLabelForRevisionView() {
    return 'Arcanist Project:';
  }

  public function renderValueForRevisionView() {
    $arcanist_phid = $this->getArcanistProjectPHID();
    if (!$arcanist_phid) {
      return null;
    }

    $handle = $this->getHandle($arcanist_phid);
    return phutil_escape_html($handle->getName());
  }

  private function getArcanistProjectPHID() {
    $diff = $this->getDiff();
    return $diff->getArcanistProjectPHID();
  }

  public function renderValueForMail($phase) {
    $status = $this->getRevision()->getStatus();

    if ($status != ArcanistDifferentialRevisionStatus::NEEDS_REVISION &&
        $status != ArcanistDifferentialRevisionStatus::ACCEPTED) {
      return null;
    }

    $diff = $this->getRevision()->loadActiveDiff();
    if ($diff) {
      $phid = $diff->getArcanistProjectPHID();
      if ($phid) {
        $handle = PhabricatorObjectHandleData::loadOneHandle($phid);
        return "ARCANIST PROJECT\n  ".$handle->getName();
      }
    }
  }

}
