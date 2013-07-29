<?php

/**
 * @group phame
 */
final class PhabricatorPhamePHIDTypeBlog extends PhabricatorPHIDType {

  const TYPECONST = 'BLOG';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Phame Blog');
  }

  public function newObject() {
    return new PhameBlog();
  }

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhameBlogQuery())
      ->setViewer($query->getViewer())
      ->withPHIDs($phids)
      ->execute();
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
    }
  }

}
