<?php

final class FundBackerPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'FBAK';

  public function getTypeName() {
    return pht('Variable');
  }

  public function newObject() {
    return new FundInitiative();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorFundApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new FundInitiativeQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $initiative = $objects[$phid];

      $id = $initiative->getID();
      $monogram = $initiative->getMonogram();
      $name = $initiative->getName();

      $handle->setName($name);
      $handle->setFullName("{$monogram} {$name}");
      $handle->setURI("/fund/view/{$id}/");
    }
  }

}
