<?php

final class PhabricatorRoleBoardRenderingEngine extends Phobject {

  private $viewer;
  private $objects;
  private $excludedRolePHIDs;
  private $editMap;

  private $loaded;
  private $handles;
  private $coverFiles;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setObjects(array $objects) {
    $this->objects = mpull($objects, null, 'getPHID');
    return $this;
  }

  public function getObjects() {
    return $this->objects;
  }

  public function setExcludedRolePHIDs(array $phids) {
    $this->excludedRolePHIDs = $phids;
    return $this;
  }

  public function getExcludedRolePHIDs() {
    return $this->excludedRolePHIDs;
  }

  public function setEditMap(array $edit_map) {
    $this->editMap = $edit_map;
    return $this;
  }

  public function getEditMap() {
    return $this->editMap;
  }

  public function renderCard($phid) {
    $this->willRender();

    $viewer = $this->getViewer();
    $object = idx($this->getObjects(), $phid);

    $card = id(new RoleBoardTaskCard())
      ->setViewer($viewer)
      ->setTask($object)
      ->setShowEditControls(true)
      ->setCanEdit($this->getCanEdit($phid));

    $owner_phid = $object->getOwnerPHID();
    if ($owner_phid) {
      $owner_handle = $this->handles[$owner_phid];
      $card->setOwner($owner_handle);
    }

    $role_phids = $object->getRolePHIDs();
    $role_handles = array_select_keys($this->handles, $role_phids);
    if ($role_handles) {
      $card
        ->setHideArchivedRoles(true)
        ->setRoleHandles($role_handles);
    }

    $cover_phid = $object->getCoverImageThumbnailPHID();
    if ($cover_phid) {
      $cover_file = idx($this->coverFiles, $cover_phid);
      if ($cover_file) {
        $card->setCoverImageFile($cover_file);
      }
    }

    return $card;
  }

  private function willRender() {
    if ($this->loaded) {
      return;
    }

    $phids = array();
    foreach ($this->objects as $object) {
      $owner_phid = $object->getOwnerPHID();
      if ($owner_phid) {
        $phids[$owner_phid] = $owner_phid;
      }

      foreach ($object->getRolePHIDs() as $phid) {
        $phids[$phid] = $phid;
      }
    }

    if ($this->excludedRolePHIDs) {
      foreach ($this->excludedRolePHIDs as $excluded_phid) {
        unset($phids[$excluded_phid]);
      }
    }

    $viewer = $this->getViewer();

    $handles = $viewer->loadHandles($phids);
    $handles = iterator_to_array($handles);
    $this->handles = $handles;

    $cover_phids = array();
    foreach ($this->objects as $object) {
      $cover_phid = $object->getCoverImageThumbnailPHID();
      if ($cover_phid) {
        $cover_phids[$cover_phid] = $cover_phid;
      }
    }

    if ($cover_phids) {
      $cover_files = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs($cover_phids)
        ->execute();
      $cover_files = mpull($cover_files, null, 'getPHID');
    } else {
      $cover_files = array();
    }

    $this->coverFiles = $cover_files;

    $this->loaded = true;
  }

  private function getCanEdit($phid) {
    if ($this->editMap === null) {
      return true;
    }

    return idx($this->editMap, $phid);
  }

}
