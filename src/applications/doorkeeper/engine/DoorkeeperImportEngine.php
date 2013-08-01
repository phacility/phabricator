<?php

final class DoorkeeperImportEngine extends Phobject {

  private $viewer;
  private $refs = array();
  private $phids = array();
  private $localOnly;

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

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function needLocalOnly($local_only) {
    $this->localOnly = $local_only;
    return $this;
  }

  public function execute() {
    $refs = $this->getRefs();
    $viewer = $this->getViewer();

    $keys = mpull($refs, 'getObjectKey');
    if ($keys) {
      $xobjs = id(new DoorkeeperExternalObjectQuery())
        ->setViewer($viewer)
        ->withObjectKeys($keys)
        ->execute();
      $xobjs = mpull($xobjs, null, 'getObjectKey');
      foreach ($refs as $ref) {
        $xobj = idx($xobjs, $ref->getObjectKey());
        if (!$xobj) {
          $xobj = $ref
            ->newExternalObject()
            ->setImporterPHID($viewer->getPHID());
        }
        $ref->attachExternalObject($xobj);
      }
    }

    if ($this->phids) {
      $xobjs = id(new DoorkeeperExternalObjectQuery())
        ->setViewer($viewer)
        ->withPHIDs($this->phids)
        ->execute();
      foreach ($xobjs as $xobj) {
        $ref = $xobj->getRef();
        $ref->attachExternalObject($xobj);
        $refs[$ref->getObjectKey()] = $ref;
      }
    }

    if (!$this->localOnly) {
      $bridges = id(new PhutilSymbolLoader())
        ->setAncestorClass('DoorkeeperBridge')
        ->loadObjects();

      foreach ($bridges as $key => $bridge) {
        if (!$bridge->isEnabled()) {
          unset($bridges[$key]);
        }
        $bridge->setViewer($viewer);
      }

      $working_set = $refs;
      foreach ($bridges as $bridge) {
        $bridge_refs = array();
        foreach ($working_set as $key => $ref) {
          if ($bridge->canPullRef($ref)) {
            $bridge_refs[$key] = $ref;
            unset($working_set[$key]);
          }
        }
        if ($bridge_refs) {
          $bridge->pullRefs($bridge_refs);
        }
      }
    }

    return $refs;
  }

  public function executeOne() {
    return head($this->execute());
  }

}
