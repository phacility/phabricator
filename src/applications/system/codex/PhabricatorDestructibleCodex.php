<?php

abstract class PhabricatorDestructibleCodex
  extends Phobject {

  private $viewer;
  private $object;

  public function getDestructionNotes() {
    return array();
  }

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setObject(
    PhabricatorDestructibleCodexInterface $object) {
    $this->object = $object;
    return $this;
  }

  final public function getObject() {
    return $this->object;
  }

  final public static function newFromObject(
    PhabricatorDestructibleCodexInterface $object,
    PhabricatorUser $viewer) {

    if (!($object instanceof PhabricatorDestructibleInterface)) {
      throw new Exception(
        pht(
          'Object (of class "%s") implements interface "%s", but must also '.
          'implement interface "%s".',
          get_class($object),
          'PhabricatorDestructibleCodexInterface',
          'PhabricatorDestructibleInterface'));
    }

    $codex = $object->newDestructibleCodex();
    if (!($codex instanceof PhabricatorDestructibleCodex)) {
      throw new Exception(
        pht(
          'Object (of class "%s") implements interface "%s", but defines '.
          'method "%s" incorrectly: this method must return an object of '.
          'class "%s".',
          get_class($object),
          'PhabricatorDestructibleCodexInterface',
          'newDestructibleCodex()',
          __CLASS__));
    }

    $codex
      ->setObject($object)
      ->setViewer($viewer);

    return $codex;
  }

}
