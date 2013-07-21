<?php

final class ReleephObjectHandleLoader {

  public function loadHandles(array $phids) {
    $types = array();

    foreach ($phids as $phid) {
      $type = phid_get_type($phid);
      $types[$type][] = $phid;
    }

    $handles = array();

    foreach ($types as $type => $phids) {
      switch ($type) {

        case ReleephPHIDConstants::PHID_TYPE_REBR:
          $object = new ReleephBranch();

          $branches = $object->loadAllWhere('phid IN (%Ls)', $phids);
          $branches = mpull($branches, null, 'getPHID');

          foreach ($phids as $phid) {
            $branch = $branches[$phid];
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            $handle->setURI($branch->getURI());
            $handle->setName($branch->getBasename());
            $handle->setFullName($branch->getName());
            $handle->setComplete(true);
            $handles[$phid] = $handle;
          }
          break;

        default:
          throw new Exception('unknown type '.$type);
      }
    }

    return $handles;
  }

}
