<?php

abstract class HarbormasterBuildAutoplan extends Phobject {

  abstract public function getAutoplanPlanKey();
  abstract public function getAutoplanName();

  public static function getAutoplan($key) {
    return idx(self::getAllAutoplans(), $key);
  }

  public static function getAllAutoplans() {
    static $plans;

    if ($plans === null) {
      $objects = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();

      $map = array();
      foreach ($objects as $object) {
        $key = $object->getAutoplanPlanKey();
        if (!empty($map[$key])) {
          $other = $map[$key];
          throw new Exception(
            pht(
              'Two build autoplans (of classes "%s" and "%s") define the same '.
              'key ("%s"). Each autoplan must have a unique key.',
              get_class($other),
              get_class($object),
              $key));
        }
        $map[$key] = $object;
      }

      ksort($map);

      $plans = $map;
    }

    return $plans;
  }

}
