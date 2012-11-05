<?php

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
