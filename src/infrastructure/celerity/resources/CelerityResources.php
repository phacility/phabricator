<?php

/**
 * Defines the location of static resources.
 */
abstract class CelerityResources {

  abstract public function getName();
  abstract public function getPathToMap();
  abstract public function getResourceData($name);
  abstract public function findBinaryResources();
  abstract public function findTextResources();

  public function getCelerityHash($data) {
    $tail = PhabricatorEnv::getEnvConfig('celerity.resource-hash');
    $hash = PhabricatorHash::digest($data, $tail);
    return substr($hash, 0, 8);
  }

  public function getResourceType($path) {
    return CelerityResourceTransformer::getResourceType($path);
  }

  public function getResourceURI($hash, $name) {
    return "/res/{$hash}/{$name}";
  }

  public static function getAll() {
    static $resources_map;
    if ($resources_map === null) {
      $resources_map = array();

      $resources_list = id(new PhutilSymbolLoader())
        ->setAncestorClass('CelerityResources')
        ->loadObjects();

      foreach ($resources_list as $resources) {
        $name = $resources->getName();
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
