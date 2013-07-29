<?php

final class PhabricatorObjectHandleData {

  private $phids;
  private $viewer;

  public function __construct(array $phids) {
    $this->phids = array_unique($phids);
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public static function loadOneHandle($phid, PhabricatorUser $viewer) {
    $query = new PhabricatorObjectHandleData(array($phid));
    $query->setViewer($viewer);
    $handles = $query->loadHandles();
    return $handles[$phid];
  }

  public function loadObjects() {
    $phids = array_fuse($this->phids);

    return id(new PhabricatorObjectQuery())
      ->setViewer($this->viewer)
      ->withPHIDs($phids)
      ->execute();
  }

  public function loadHandles() {

    $application_handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->viewer)
      ->withPHIDs($this->phids)
      ->execute();

    // TODO: Move the rest of this into Applications.

    $phid_map = array_fuse($this->phids);
    foreach ($application_handles as $handle) {
      if ($handle->isComplete()) {
        unset($phid_map[$handle->getPHID()]);
      }
    }

    $types = phid_group_by_type($phid_map);

    $handles = array();
    foreach ($types as $type => $phids) {
      switch ($type) {
        case PhabricatorPHIDConstants::PHID_TYPE_MAGIC:
          // Black magic!
          foreach ($phids as $phid) {
            $handle = new PhabricatorObjectHandle();
            $handle->setPHID($phid);
            $handle->setType($type);
            switch ($phid) {
              case ManiphestTaskOwner::OWNER_UP_FOR_GRABS:
                $handle->setName('Up For Grabs');
                $handle->setFullName('upforgrabs (Up For Grabs)');
                $handle->setComplete(true);
                break;
              case ManiphestTaskOwner::PROJECT_NO_PROJECT:
                $handle->setName('No Project');
                $handle->setFullName('noproject (No Project)');
                $handle->setComplete(true);
                break;
              default:
                $handle->setName('Foul Magicks');
                break;
            }
            $handles[$phid] = $handle;
          }
          break;
      }
    }

    return $handles + $application_handles;
  }
}
