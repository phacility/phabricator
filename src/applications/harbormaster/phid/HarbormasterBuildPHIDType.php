<?php

final class HarbormasterBuildPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'HMBD';

  public function getTypeName() {
    return pht('Build');
  }

  public function newObject() {
    return new HarbormasterBuild();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new HarbormasterBuildQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $build = $objects[$phid];
      $handles[$phid]->setName(pht(
        'Build %d: %s',
        $build->getID(),
        $build->getName()));
      $handles[$phid]->setURI(
        '/harbormaster/build/'.$build->getID());
    }
  }

}
