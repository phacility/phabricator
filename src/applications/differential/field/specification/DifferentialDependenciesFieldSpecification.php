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

final class DifferentialDependenciesFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function getRequiredHandlePHIDsForRevisionView() {
    return $this->getDependentRevisionPHIDs();
  }

  public function renderLabelForRevisionView() {
    return 'Depends On:';
  }

  public function renderValueForRevisionView() {
    $revision_phids = $this->getDependentRevisionPHIDs();
    if (!$revision_phids) {
      return null;
    }

    $links = array();
    foreach ($revision_phids as $revision_phids) {
      $links[] = $this->getHandle($revision_phids)->renderLink();
    }

    return implode('<br />', $links);
  }

  private function getDependentRevisionPHIDs() {
    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $this->getRevision()->getPHID(),
      PhabricatorEdgeConfig::TYPE_DREV_DEPENDS_ON_DREV);
  }

}
