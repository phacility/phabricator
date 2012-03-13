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

final class PhabricatorProjectAffiliation extends PhabricatorProjectDAO {

  protected $projectPHID;
  protected $userPHID;
  protected $role;
  protected $isOwner = 0;

  public static function loadAllForProjectPHIDs($phids) {
    if (!$phids) {
      return array();
    }
    $default = array_fill_keys($phids, array());

    $affiliations = id(new PhabricatorProjectAffiliation())->loadAllWhere(
      'projectPHID IN (%Ls) ORDER BY dateCreated',
      $phids);

    return mgroup($affiliations, 'getProjectPHID') + $default;
  }

}
