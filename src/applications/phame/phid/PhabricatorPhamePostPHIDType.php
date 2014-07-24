<?php

final class PhabricatorPhamePostPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'POST';

  public function getTypeName() {
    return pht('Phame Post');
  }

  public function newObject() {
    return new PhamePost();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhamePostQuery())
      ->withPHIDs($phids);
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
