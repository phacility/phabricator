<?php

final class PhabricatorLegalpadDocumentPHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'LEGD';

  public function getTypeName() {
    return pht('Legalpad Document');
  }

  public function getTypeIcon() {
    return 'fa-file-text-o';
  }

  public function newObject() {
    return new LegalpadDocument();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorLegalpadApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new LegalpadDocumentQuery())
      ->withPHIDs($phids)
      ->needDocumentBodies(true);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $document = $objects[$phid];
      $name = $document->getDocumentBody()->getTitle();
      $handle->setName($document->getMonogram().' '.$name);
      $handle->setURI('/'.$document->getMonogram());
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^L\d*[1-9]\d*$/i', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      $id = (int)substr($name, 1);
      $id_map[$id][] = $name;
    }

    $objects = id(new LegalpadDocumentQuery())
      ->setViewer($query->getViewer())
      ->withIDs(array_keys($id_map))
      ->execute();

    $results = array();
    foreach ($objects as $id => $object) {
      foreach (idx($id_map, $id, array()) as $name) {
        $results[$name] = $object;
      }
    }

    return $results;
  }

}
