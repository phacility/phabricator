<?php

final class DoorkeeperImportEngine extends Phobject {

  private $viewer;
  private $refs;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setRefs(array $refs) {
    assert_instances_of($refs, 'DoorkeeperObjectRef');
    $this->refs = $refs;
    return $this;
  }

  public function getRefs() {
    return $this->refs;
  }

  public function execute() {
    $refs = $this->getRefs();
    $viewer = $this->getViewer();

    $keys = mpull($refs, 'getObjectKey');
    if ($keys) {
      $xobjs = id(new DoorkeeperExternalObject())->loadAllWhere(
        'objectKey IN (%Ls)',
        $keys);
      $xobjs = mpull($xobjs, null, 'getObjectKey');
      foreach ($refs as $ref) {
        $xobj = idx($xobjs, $ref->getObjectKey());
        if (!$xobj) {
          $xobj = $ref->newExternalObject()
            ->setImporterPHID($viewer->getPHID());
        }
        $ref->attachExternalObject($xobj);
      }
    }

    $bridges = id(new PhutilSymbolLoader())
      ->setAncestorClass('DoorkeeperBridge')
      ->loadObjects();

    foreach ($bridges as $key => $bridge) {
      if (!$bridge->isEnabled()) {
        unset($bridges[$key]);
      }
      $bridge->setViewer($viewer);
    }

    foreach ($bridges as $bridge) {
      $bridge_refs = array();
      foreach ($refs as $key => $ref) {
        if ($bridge->canPullRef($ref)) {
          $bridge_refs[$key] = $ref;
          unset($refs[$key]);
        }
      }
      if ($bridge_refs) {
        $bridge->pullRefs($bridge_refs);
      }
    }

    return $this->getRefs();
  }

}
