<?php

final class PhabricatorPhameBlogPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'BLOG';

  public function getTypeName() {
    return pht('Phame Blog');
  }

  public function newObject() {
    return new PhameBlog();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhameApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhameBlogQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $blog = $objects[$phid];
      $handle->setName($blog->getName());
      $handle->setFullName($blog->getName());
      $handle->setURI('/phame/blog/view/'.$blog->getID().'/');

      if ($blog->isArchived()) {
        $handle->setStatus(PhabricatorObjectHandle::STATUS_CLOSED);
      }

    }
  }

}
