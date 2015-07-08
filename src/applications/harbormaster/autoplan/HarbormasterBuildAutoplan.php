<?php

abstract class HarbormasterBuildAutoplan extends Phobject {

  abstract public function getAutoplanPlanKey();
  abstract public function getAutoplanName();

  public static function getAutoplan($key) {
    return idx(self::getAllAutoplans(), $key);
  }

  public static function getAllAutoplans() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getAutoplanPlanKey')
      ->execute();
  }

}
