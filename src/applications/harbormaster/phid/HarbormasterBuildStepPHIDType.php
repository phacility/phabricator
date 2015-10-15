<?php

final class HarbormasterBuildStepPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'HMCS';

  public function getTypeName() {
    return pht('Build Step');
  }

  public function newObject() {
    return new HarbormasterBuildStep();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new HarbormasterBuildStepQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $build_step = $objects[$phid];

      $id = $build_step->getID();
      $name = $build_step->getName();

      $handle
        ->setName($name)
        ->setFullName(pht('Build Step %d: %s', $id, $name))
        ->setURI("/harbormaster/step/{$id}/edit/");
    }
  }

}
