<?php

final class PhragmentFragmentPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PHRF';

  public function getTypeName() {
    return pht('Fragment');
  }

  public function newObject() {
    return new PhragmentFragment();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhragmentFragmentQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    $viewer = $query->getViewer();
    foreach ($handles as $phid => $handle) {
      $fragment = $objects[$phid];

      $handle->setName(pht(
        'Fragment %s: %s',
        $fragment->getID(),
        $fragment->getName()));
      $handle->setURI($fragment->getURI());
    }
  }

}
