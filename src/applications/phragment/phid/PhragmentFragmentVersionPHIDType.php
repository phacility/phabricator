<?php

final class PhragmentFragmentVersionPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'PHRV';

  public function getTypeName() {
    return pht('Fragment Version');
  }

  public function newObject() {
    return new PhragmentFragmentVersion();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhragmentApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhragmentFragmentVersionQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    $viewer = $query->getViewer();
    foreach ($handles as $phid => $handle) {
      $version = $objects[$phid];

      $handle->setName(pht(
        'Fragment Version %d: %s',
        $version->getSequence(),
        $version->getFragment()->getName()));
      $handle->setURI($version->getURI());
    }
  }

}
