<?php

/**
 * Coordinates loading object handles.
 *
 * This is a low-level piece of plumbing which code will not normally interact
 * with directly. For discussion of the handle pool mechanism, see
 * @{class:PhabricatorHandleList}.
 */
final class PhabricatorHandlePool extends Phobject {

  private $viewer;
  private $handles = array();
  private $unloadedPHIDs = array();

  public function setViewer(PhabricatorUser $user) {
    $this->viewer = $user;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function newHandleList(array $phids) {
    // Mark any PHIDs we haven't loaded yet as unloaded. This will let us bulk
    // load them later.
    foreach ($phids as $phid) {
      if (empty($this->handles[$phid])) {
        $this->unloadedPHIDs[$phid] = true;
      }
    }

    $unique = array();
    foreach ($phids as $phid) {
      $unique[$phid] = $phid;
    }

    return id(new PhabricatorHandleList())
      ->setHandlePool($this)
      ->setPHIDs(array_values($unique));
  }

  public function loadPHIDs(array $phids) {
    $need = array();
    foreach ($phids as $phid) {
      if (empty($this->handles[$phid])) {
        $need[$phid] = true;
      }
    }

    foreach ($need as $phid => $ignored) {
      if (empty($this->unloadedPHIDs[$phid])) {
        throw new Exception(
          pht(
            'Attempting to load PHID "%s", but it was not requested by any '.
            'handle list.',
            $phid));
      }
    }

    // If we need any handles, bulk load everything in the queue.
    if ($need) {
      // Clear the list of PHIDs that need to be loaded before performing the
      // actual fetch. This prevents us from looping if we need to reenter the
      // HandlePool while loading handles.
      $fetch_phids = array_keys($this->unloadedPHIDs);
      $this->unloadedPHIDs = array();

      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($fetch_phids)
        ->execute();
      $this->handles += $handles;
    }

    return array_select_keys($this->handles, $phids);
  }

}
