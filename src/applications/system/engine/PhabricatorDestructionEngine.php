<?php

final class PhabricatorDestructionEngine extends Phobject {

  private $rootLogID;

  public function destroyObject(PhabricatorDestructibleInterface $object) {
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

      if ($object instanceof PhabricatorApplicationTransactionInterface) {
        $template = $object->getApplicationTransactionTemplate();
        $this->destroyTransactions($template, $object_phid);
      }
    }

    // TODO: PhabricatorFlaggableInterface
    // TODO: PhabricatorTokenReceiverInterface
  }

  private function destroyEdges($src_phid) {
    try {
      $edges = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(array($src_phid))
        ->execute();
    } catch (Exception $ex) {
      // This is (presumably) a "no edges for this PHID type" exception.
      return;
    }

    $editor = new PhabricatorEdgeEditor();
    foreach ($edges as $type => $type_edges) {
      foreach ($type_edges as $src => $src_edges) {
        foreach ($src_edges as $dst => $edge) {
          $editor->removeEdge($edge['src'], $edge['type'], $edge['dst']);
        }
      }
    }
    $editor->save();
  }

  private function destroyTransactions(
    PhabricatorApplicationTransaction $template,
    $object_phid) {

    $xactions = $template->loadAllWhere('objectPHID = %s', $object_phid);
    foreach ($xactions as $xaction) {
      $this->destroyObject($xaction);
    }

  }

}
