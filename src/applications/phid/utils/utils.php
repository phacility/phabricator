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

/**
 * Look up the type of a PHID. Returns
 * PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN if it fails to look up the type
 *
 * @param   phid Anything.
 * @return  A value from PhabricatorPHIDConstants (ideally)
 */
function phid_get_type($phid) {
  $matches = null;
  if (is_string($phid) && preg_match('/^PHID-([^-]{4})-/', $phid, $matches)) {
    return $matches[1];
  }
  return PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN;
}

/**
 * Group a list of phids by type. Given:
 *
 *   phid_group_by_type([PHID-USER-1, PHID-USER-2, PHID-PROJ-3])
 *
 * phid_group_by_type would return:
 *
 *   [PhabricatorPHIDConstants::PHID_TYPE_USER => [PHID-USER-1, PHID-USER-2],
 *    PhabricatorPHIDConstants::PHID_TYPE_PROJ => [PHID-PROJ-3]]
 *
 * @param   phids array of phids
 * @return  map of phid type => list of phids
 */
function phid_group_by_type($phids) {
  $result = array();
  foreach ($phids as $phid) {
    $type = phid_get_type($phid);
    $result[$type][] = $phid;
  }
  return $result;
}
