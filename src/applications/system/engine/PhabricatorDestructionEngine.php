<?php

final class PhabricatorDestructionEngine extends Phobject {

  private $rootLogID;

  public function destroyObject(PhabricatorDestructableInterface $object) {
    $log = id(new PhabricatorSystemDestructionLog())
      ->setEpoch(time())
      ->setObjectClass(get_class($object));

    if ($this->rootLogID) {
      $log->setRootLogID($this->rootLogID);
    }

    $object_phid = null;
    if (method_exists($object, 'getPHID')) {
      try {
        $object_phid = $object->getPHID();
        $log->setObjectPHID($object_phid);
      } catch (Exception $ex) {
        // Ignore.
      }
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

    $object->destroyObjectPermanently($this);

    if ($object_phid) {
      $this->destroyEdges($object_phid);
    }
  }

  private function destroyEdges($src_phid) {
    $edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array($src_phid))
      ->execute();

    $editor = id(new PhabricatorEdgeEditor())
      ->setSuppressEvents(true);
    foreach ($edges as $edge) {
      $editor->removeEdge($edge['src'], $edge['type'], $edge['dst']);
    }
    $editor->save();
  }

}
