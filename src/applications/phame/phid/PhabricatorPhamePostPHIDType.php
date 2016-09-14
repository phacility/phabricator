<?php

final class PhabricatorPhamePostPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'POST';

  public function getTypeName() {
    return pht('Phame Post');
  }

  public function newObject() {
    return new PhamePost();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhameApplication';
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
      $handle->setFullName(pht('Blog Post: ').$post->getTitle());
      $handle->setURI('/J'.$post->getID());

      if ($post->isArchived()) {
        $handle->setStatus(PhabricatorObjectHandle::STATUS_CLOSED);
      }

    }

  }

}
