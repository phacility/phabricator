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

final class PhabricatorApplicationDifferential extends PhabricatorApplication {

  public function getBaseURI() {
    return '/differential/';
  }

  public function getShortDescription() {
    return 'Review Code';
  }

  public function getIconURI() {
    return celerity_get_resource_uri('/rsrc/image/app/app_differential.png');
  }

  public function getFactObjectsForAnalysis() {
    return array(
      new DifferentialRevision(),
    );
  }

  public function loadStatus(PhabricatorUser $user) {
    $revisions = id(new DifferentialRevisionQuery())
      ->withResponsibleUsers(array($user->getPHID()))
      ->withStatus(DifferentialRevisionQuery::STATUS_OPEN)
      ->execute();

    list($active, $waiting) = DifferentialRevisionQuery::splitResponsible(
      $revisions,
      $user->getPHID());

    $status = array();

    $active = count($active);
    $type = $active
      ? PhabricatorApplicationStatusView::TYPE_NEEDS_ATTENTION
      : PhabricatorApplicationStatusView::TYPE_EMPTY;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Review(s) Need Attention', $active))
      ->setCount($active);

    $waiting = count($waiting);
    $type = $waiting
      ? PhabricatorApplicationStatusView::TYPE_INFO
      : PhabricatorApplicationStatusView::TYPE_EMPTY;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Review(s) Waiting on Others', $waiting));

    return $status;
  }

}

