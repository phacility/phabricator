<?php

final class PhabricatorRepositoryRepositoryPHIDType
  extends PhabricatorPHIDType {

  const TYPECONST = 'REPO';

  public function getTypeName() {
    return pht('Repository');
  }

  public function getTypeIcon() {
    return 'fa-code';
  }

  public function newObject() {
    return new PhabricatorRepository();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhabricatorRepositoryQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $repository = $objects[$phid];

      $monogram = $repository->getMonogram();
      $name = $repository->getName();
      $uri = $repository->getURI();

      $handle
        ->setName($monogram)
        ->setFullName("{$monogram} {$name}")
        ->setURI($uri)
        ->setMailStampName($monogram);

      if ($repository->getStatus() !== PhabricatorRepository::STATUS_ACTIVE) {
        $handle->setStatus(PhabricatorObjectHandle::STATUS_CLOSED);
      }
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^(r[A-Z]+|R[1-9]\d*)\z/', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $results = array();
    $id_map = array();
    foreach ($names as $key => $name) {
      $id = substr($name, 1);
      $id_map[$id][] = $name;
      $names[$key] = substr($name, 1);
    }

    $query = id(new PhabricatorRepositoryQuery())
      ->setViewer($query->getViewer())
      ->withIdentifiers($names);

    if ($query->execute()) {
      $objects = $query->getIdentifierMap();
      foreach ($objects as $key => $object) {
        foreach (idx($id_map, $key, array()) as $name) {
          $results[$name] = $object;
        }
      }
      return $results;
    } else {
      return array();
    }
  }

}
