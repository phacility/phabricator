<?php

/**
 * @group phame
 */
final class PhabricatorPhamePHIDTypePost extends PhabricatorPHIDType {

  const TYPECONST = 'POST';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Phame Post');
  }

  public function newObject() {
    return new PhamePost();
  }

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhamePostQuery())
      ->setViewer($query->getViewer())
      ->withPHIDs($phids)
      ->execute();
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $post = $objects[$phid];
      $handle->setName($post->getTitle());
      $handle->setFullName($post->getTitle());
      $handle->setURI('/phame/post/view/'.$post->getID().'/');
    }
  }

}
