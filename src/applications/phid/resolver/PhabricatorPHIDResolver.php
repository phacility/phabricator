<?php

/**
 * Resolve a list of identifiers into PHIDs.
 *
 * This class simplifies the process of convering a list of mixed token types
 * (like some PHIDs and some usernames) into a list of just PHIDs.
 */
abstract class PhabricatorPHIDResolver extends Phobject {

  private $viewer;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function resolvePHIDs(array $phids) {
    $type_unknown = PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN;

    $names = array();
    foreach ($phids as $key => $phid) {
      if (phid_get_type($phid) == $type_unknown) {
        $names[$key] = $phid;
      }
    }

    if ($names) {
      $map = $this->getResolutionMap($names);
      foreach ($names as $key => $name) {
        if (isset($map[$name])) {
          $phids[$key] = $map[$name];
        }
      }
    }

    return $phids;
  }

  abstract protected function getResolutionMap(array $names);

}
