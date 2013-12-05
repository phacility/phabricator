<?php

final class DrydockPHIDTypeBlueprint extends PhabricatorPHIDType {

  const TYPECONST = 'DRYB';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Blueprint');
  }

  public function newObject() {
    return new DrydockBlueprint();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new DrydockBlueprintQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

  }

}
