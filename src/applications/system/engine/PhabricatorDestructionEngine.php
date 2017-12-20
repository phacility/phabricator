<?php

final class PhabricatorDestructionEngine extends Phobject {

  private $rootLogID;
  private $collectNotes;
  private $notes = array();

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

  public function destroyObject(PhabricatorDestructibleInterface $object) {
    $log = id(new PhabricatorSystemDestructionLog())
      ->setEpoch(time())
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
    }
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
