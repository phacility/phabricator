<?php

/**
 * Defines the location of static resources.
 */
abstract class CelerityResources extends Phobject {

  abstract public function getName();
  abstract public function getResourceData($name);

  public function getResourceModifiedTime($name) {
    return 0;
  }

  public function getCelerityHash($data) {
    $tail = PhabricatorEnv::getEnvConfig('celerity.resource-hash');
    $hash = PhabricatorHash::weakDigest($data, $tail);
    return substr($hash, 0, 8);
  }

  public function getResourceType($path) {
    return CelerityResourceTransformer::getResourceType($path);
  }

  public function getResourceURI($hash, $name) {
    $resources = $this->getName();
    return "/res/{$resources}/{$hash}/{$name}";
  }

  public function getResourcePackages() {
    return array();
  }

  public function loadMap() {
    return array();
  }

}
