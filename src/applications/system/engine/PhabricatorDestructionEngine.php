<?php

final class PhabricatorDestructionEngine extends Phobject {

  private $rootLogID;
  private $collectNotes;
  private $notes = array();
  private $depth = 0;
  private $destroyedObjects = array();
  private $waitToFinalizeDestruction = false;

  public function setCollectNotes($collect_notes) {
    $this->collectNotes = $collect_notes;
    return $this;
  }

  public function getNotes() {
    return $this->notes;
  }

  public function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  public function setWaitToFinalizeDestruction($wait) {
    $this->waitToFinalizeDestruction = $wait;
    return $this;
  }

  public function getWaitToFinalizeDestruction() {
    return $this->waitToFinalizeDestruction;
  }

  public function destroyObject(PhabricatorDestructibleInterface $object) {
    $this->depth++;

    $log = id(new PhabricatorSystemDestructionLog())
      ->setEpoch(PhabricatorTime::getNow())
      ->setObjectClass(get_class($object));

    if ($this->rootLogID) {
      $log->setRootLogID($this->rootLogID);
    }

    $object_phid = $this->getObjectPHID($object);
    if ($object_phid) {
      $log->setObjectPHID($object_phid);
    }

    if (method_exists($object, 'getMonogram')) {
      try {
        $log->setObjectMonogram($object->getMonogram());
      } catch (Exception $ex) {
        // Ignore.
      }
    }

    $log->save();

    if (!$this->rootLogID) {
      $this->rootLogID = $log->getID();
    }

    if ($this->collectNotes) {
      if ($object instanceof PhabricatorDestructibleCodexInterface) {
        $codex = PhabricatorDestructibleCodex::newFromObject(
          $object,
          $this->getViewer());

        foreach ($codex->getDestructionNotes() as $note) {
          $this->notes[] = $note;
        }
      }
    }

    $object->destroyObjectPermanently($this);

    if ($object_phid) {
      $extensions = PhabricatorDestructionEngineExtension::getAllExtensions();
      foreach ($extensions as $key => $extension) {
        if (!$extension->canDestroyObject($this, $object)) {
          unset($extensions[$key]);
          continue;
        }
      }

      foreach ($extensions as $key => $extension) {
        $extension->destroyObject($this, $object);
      }

      $this->destroyedObjects[] = $object;
    }

    $this->depth--;

    // If this is a root-level invocation of "destroyObject()", flush the
    // queue of destroyed objects and fire "didDestroyObject()" hooks. This
    // hook allows extensions to do things like queue cache updates which
    // might race if we fire them during object destruction.

    if (!$this->depth) {
      if (!$this->getWaitToFinalizeDestruction()) {
        $this->finalizeDestruction();
      }
    }

    return $this;
  }

  public function finalizeDestruction() {
    $extensions = PhabricatorDestructionEngineExtension::getAllExtensions();

    foreach ($this->destroyedObjects as $object) {
      foreach ($extensions as $extension) {
        if (!$extension->canDestroyObject($this, $object)) {
          continue;
        }

        $extension->didDestroyObject($this, $object);
      }
    }

    $this->destroyedObjects = array();

    return $this;
  }

  private function getObjectPHID($object) {
    if (!is_object($object)) {
      return null;
    }

    if (!method_exists($object, 'getPHID')) {
      return null;
    }

    try {
      return $object->getPHID();
    } catch (Exception $ex) {
      return null;
    }
  }

}
