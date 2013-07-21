<?php

abstract class PhabricatorPHIDType {

  abstract public function getTypeConstant();
  abstract public function getTypeName();

  public function newObject() {
    return null;
  }

  abstract public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids);
  abstract public function loadHandles(array $phids, array $objects);

  public function canLoadNamedObject($name) {
    return false;
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {
    throw new Exception("Not implemented!");
  }

  public static function getAllTypes() {
    static $types;
    if ($types === null) {
      $objects = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();

      $map = array();
      $original = array();
      foreach ($objects as $object) {
        $type = $object->getTypeConstant();
        if (isset($map[$type])) {
          $that_class = $original[$type];
          $this_class = get_class($object);
          throw new Exception(
            "Two PhabricatorPHIDType classes ({$that_class}, {$this_class}) ".
            "both handle PHID type '{$type}'. A type may be handled by only ".
            "one class.");
        }

        $original[$type] = get_class($object);
        $map[$type] = $object;
      }

      $types = $map;
    }

    return $types;
  }

}
