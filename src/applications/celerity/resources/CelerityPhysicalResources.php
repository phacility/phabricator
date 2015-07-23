<?php

/**
 * Defines the location of physical static resources which exist at build time
 * and are precomputed into a resource map.
 */
abstract class CelerityPhysicalResources extends CelerityResources {

  private $map;

  abstract public function getPathToMap();
  abstract public function findBinaryResources();
  abstract public function findTextResources();

  public function loadMap() {
    if ($this->map === null) {
      $this->map = include $this->getPathToMap();
    }
    return $this->map;
  }

  public static function getAll() {
    static $resources_map;

    if ($resources_map === null) {
      $resources_list = id(new PhutilClassMapQuery())
        ->setAncestorClass(__CLASS__)
        ->setUniqueMethod('getName')
        ->execute();

      foreach ($resources_list as $resources) {
        $name = $resources->getName();

        if (!preg_match('/^[a-z0-9]+/', $name)) {
          throw new Exception(
            pht(
              'Resources name "%s" is not valid; it must contain only '.
              'lowercase latin letters and digits.',
              $name));
        }
      }

      $resources_map = $resources_list;
    }

    return $resources_map;
  }

}
