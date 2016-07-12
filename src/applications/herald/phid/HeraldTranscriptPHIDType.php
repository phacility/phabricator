<?php

final class HeraldTranscriptPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'HLXS';

  public function getTypeName() {
    return pht('Herald Transcript');
  }

  public function newObject() {
    return new HeraldTranscript();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorHeraldApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new HeraldTranscriptQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $xscript = $objects[$phid];

      $id = $xscript->getID();

      $handle->setName(pht('Transcript %s', $id));
      $handle->setURI("/herald/transcript/${id}/");
    }
  }

}
