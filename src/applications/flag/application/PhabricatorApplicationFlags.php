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

final class PhabricatorApplicationFlags extends PhabricatorApplication {

  public function getShortDescription() {
    return 'Reminders';
  }

  public function getBaseURI() {
    return '/flag/';
  }

  public function getIconURI() {
    return celerity_get_resource_uri('/rsrc/image/app/app_flags.png');
  }

  public function loadStatus(PhabricatorUser $user) {
    $status = array();

    $flags = id(new PhabricatorFlagQuery())
      ->withOwnerPHIDs(array($user->getPHID()))
      ->execute();

    $count = count($flags);
    $type = $count
      ? PhabricatorApplicationStatusView::TYPE_INFO
      : PhabricatorApplicationStatusView::TYPE_EMPTY;
    $status[] = id(new PhabricatorApplicationStatusView())
      ->setType($type)
      ->setText(pht('%d Flagged Object(s)', $count))
      ->setCount($count);

    return $status;
  }

}

