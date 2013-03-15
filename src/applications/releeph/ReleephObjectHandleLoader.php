<?php

final class ReleephObjectHandleLoader extends ObjectHandleLoader {

  /**
   * The intention for phid.external-loaders is for each new 4-char PHID type
   * to point to a different external loader for that type.
   *
   * For brevity, we instead just have this one class that can load any type of
   * Releeph PHID.
   */

  public function loadHandles(array $phids) {
    $types = array();

    foreach ($phids as $phid) {
      $type = phid_get_type($phid);
      $types[$type][] = $phid;
    }

    $handles = array();

    foreach ($types as $type => $phids) {
      switch ($type) {
        case ReleephPHIDConstants::PHID_TYPE_RERQ:
          $object = new ReleephRequest();

          $instances = $object->loadAllWhere('phid in (%Ls)', $phids);
          $instances = mpull($instances, null, 'getPHID');

          foreach ($phids as $phid) {
            $instance = $instances[$phid];
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            $handle->setURI('/RQ'.$instance->getID());

            $name = 'RQ'.$instance->getID();
            $handle->setName($name);
            $handle->setFullName($name.': '.$instance->getSummaryForDisplay());
            $handle->setComplete(true);

            $handles[$phid] = $handle;
          }
          break;

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

        case ReleephPHIDConstants::PHID_TYPE_REPR:
          $object = new ReleephProject();

          $instances = $object->loadAllWhere('phid IN (%Ls)', $phids);
          $instances = mpull($instances, null, 'getPHID');

          foreach ($phids as $phid) {
            $instance = $instances[$phid];
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            $handle->setURI($instance->getURI());
            $handle->setName($instance->getName()); // no fullName for proejcts
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
