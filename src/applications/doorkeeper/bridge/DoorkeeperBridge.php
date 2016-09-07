<?php

abstract class DoorkeeperBridge extends Phobject {

  private $viewer;
  private $context = array();
  private $throwOnMissingLink;

  public function setThrowOnMissingLink($throw_on_missing_link) {
    $this->throwOnMissingLink = $throw_on_missing_link;
    return $this;
  }

  final public function setViewer($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setContext($context) {
    $this->context = $context;
    return $this;
  }

  final public function getContextProperty($key, $default = null) {
    return idx($this->context, $key, $default);
  }

  public function isEnabled() {
    return true;
  }

  abstract public function canPullRef(DoorkeeperObjectRef $ref);
  abstract public function pullRefs(array $refs);

  public function fillObjectFromData(DoorkeeperExternalObject $obj, $result) {
    return;
  }

  public function didFailOnMissingLink() {
    if ($this->throwOnMissingLink) {
      throw new DoorkeeperMissingLinkException();
    }

    return null;
  }

  final protected function saveExternalObject(
    DoorkeeperObjectRef $ref,
    DoorkeeperExternalObject $obj) {

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      try {
        $obj->save();
      } catch (AphrontDuplicateKeyQueryException $ex) {

        // In various cases, we may race another process importing the same
        // data. If we do, we'll collide on the object key. Load the object
        // the other process created and use that.
        $obj = id(new DoorkeeperExternalObjectQuery())
          ->setViewer($this->getViewer())
          ->withObjectKeys(array($ref->getObjectKey()))
          ->executeOne();
        if (!$obj) {
          throw new PhutilProxyException(
            pht('Failed to load external object after collision.'),
            $ex);
        }

        $ref->attachExternalObject($obj);
      }
    unset($unguarded);
  }


}
