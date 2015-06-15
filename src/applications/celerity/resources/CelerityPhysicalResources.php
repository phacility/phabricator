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
      $resources_map = array();

      $resources_list = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();

      foreach ($resources_list as $resources) {
        $name = $resources->getName();

        if (!preg_match('/^[a-z0-9]+/', $name)) {
          throw new Exception(
            pht(
              'Resources name "%s" is not valid; it must contain only '.
              'lowercase latin letters and digits.',
              $name));
        }

        if (empty($resources_map[$name])) {
          $resources_map[$name] = $resources;
        } else {
          $old = get_class($resources_map[$name]);
          $new = get_class($resources);
          throw new Exception(
            pht(
              'Celerity resource maps must have unique names, but maps %s and '.
              '%s share the same name, "%s".',
              $old,
              $new,
              $name));
        }
      }
    }

    return $resources_map;
  }

}
