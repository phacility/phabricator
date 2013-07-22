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
 * Group a list of phids by type.
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

function phid_get_subtype($phid) {
  if (isset($phid[14]) && ($phid[14] == '-')) {
    return substr($phid, 10, 4);
  }
  return null;
}
