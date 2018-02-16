<?php

final class PhrictionContentPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'WRDS';

  public function getTypeName() {
    return pht('Phriction Content');
  }

  public function newObject() {
    return new PhrictionContent();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorPhrictionApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhrictionContentQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $content = $objects[$phid];
    }
  }

}
