<?php

final class DoorkeeperImportEngine extends Phobject {

  private $viewer;
  private $refs = array();
  private $phids = array();
  private $localOnly;
  private $throwOnMissingLink;

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


  /**
   * Configure behavior if remote refs can not be retrieved because an
   * authentication link is missing.
   */
  public function setThrowOnMissingLink($throw) {
    $this->throwOnMissingLink = $throw;
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

          // NOTE: Fill the new external object into the object map, so we'll
          // reference the same external object if more than one ref is the
          // same. This prevents issues later where we double-populate
          // external objects when handed duplicate refs.
          $xobjs[$ref->getObjectKey()] = $xobj;
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
      $bridges = id(new PhutilClassMapQuery())
        ->setAncestorClass('DoorkeeperBridge')
        ->setFilterMethod('isEnabled')
        ->execute();

      foreach ($bridges as $key => $bridge) {
        $bridge->setViewer($viewer);
        $bridge->setThrowOnMissingLink($this->throwOnMissingLink);
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
