<?php

final class HeraldRulePHIDType extends PhabricatorPHIDType {

  const TYPECONST = 'HRUL';

  public function getTypeName() {
    return pht('Herald Rule');
  }

  public function newObject() {
    return new HeraldRule();
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorHeraldApplication';
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new HeraldRuleQuery())
      ->withPHIDs($phids);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $rule = $objects[$phid];

      $monogram = $rule->getMonogram();
      $name     = $rule->getName();

      $handle->setName($monogram);
      $handle->setFullName("{$monogram} {$name}");
      $handle->setURI("/{$monogram}");

      if ($rule->getIsDisabled()) {
        $handle->setStatus(PhabricatorObjectHandle::STATUS_CLOSED);
      }
    }
  }

  public function canLoadNamedObject($name) {
    return preg_match('/^H\d*[1-9]\d*$/i', $name);
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {

    $id_map = array();
    foreach ($names as $name) {
      $id = (int)substr($name, 1);
      $id_map[$id][] = $name;
    }

    $objects = id(new HeraldRuleQuery())
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
