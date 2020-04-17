<?php

final class PhabricatorChangesetViewStateEngine
  extends Phobject {

  private $viewer;
  private $objectPHID;
  private $changeset;
  private $storage;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setObjectPHID($object_phid) {
    $this->objectPHID = $object_phid;
    return $this;
  }

  public function getObjectPHID() {
    return $this->objectPHID;
  }

  public function setChangeset(DifferentialChangeset $changeset) {
    $this->changeset = $changeset;
    return $this;
  }

  public function getChangeset() {
    return $this->changeset;
  }

  public function newViewStateFromRequest(AphrontRequest $request) {
    $storage = $this->loadViewStateStorage();

    $this->setStorage($storage);

    $highlight = $request->getStr('highlight');
    if ($highlight !== null && strlen($highlight)) {
      $this->setChangesetProperty('highlight', $highlight);
    }

    $this->saveViewStateStorage();

    $state = new PhabricatorChangesetViewState();

    $highlight_language = $this->getChangesetProperty('highlight');
    $state->setHighlightLanguage($highlight_language);

    return $state;
  }

  private function setStorage(DifferentialViewState $storage) {
    $this->storage = $storage;
    return $this;
  }

  private function getStorage() {
    return $this->storage;
  }

  private function setChangesetProperty(
    $key,
    $value) {

    $storage = $this->getStorage();
    $changeset = $this->getChangeset();

    $storage->setChangesetProperty($changeset, $key, $value);
  }

  private function getChangesetProperty(
    $key,
    $default = null) {

    $storage = $this->getStorage();
    $changeset = $this->getChangeset();

    return $storage->getChangesetProperty($changeset, $key, $default);
  }

  private function loadViewStateStorage() {
    $viewer = $this->getViewer();

    $object_phid = $this->getObjectPHID();
    $viewer_phid = $viewer->getPHID();

    $storage = null;

    if ($viewer_phid !== null) {
      $storage = id(new DifferentialViewStateQuery())
        ->setViewer($viewer)
        ->withViewerPHIDs(array($viewer_phid))
        ->withObjectPHIDs(array($object_phid))
        ->executeOne();
    }

    if ($storage === null) {
      $storage = id(new DifferentialViewState())
        ->setObjectPHID($object_phid);

      if ($viewer_phid !== null) {
        $storage->setViewerPHID($viewer_phid);
      } else {
        $storage->makeEphemeral();
      }
    }

    return $storage;
  }

  private function saveViewStateStorage() {
    if (PhabricatorEnv::isReadOnly()) {
      return;
    }

    $storage = $this->getStorage();

    $viewer_phid = $storage->getViewerPHID();
    if ($viewer_phid === null) {
      return;
    }

    if (!$storage->getHasModifications()) {
      return;
    }

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    try {
      $storage->save();
    } catch (AphrontDuplicateKeyQueryException $ex) {
      // We may race another process to save view state. For now, just discard
      // our state if we do.
    }

    unset($unguarded);
  }

}
