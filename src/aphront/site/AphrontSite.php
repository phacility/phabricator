<?php

abstract class AphrontSite extends Phobject {

  abstract public function getPriority();
  abstract public function getDescription();

  abstract public function shouldRequireHTTPS();
  abstract public function newSiteForRequest(AphrontRequest $request);

  /**
   * NOTE: This is temporary glue; eventually, sites will return an entire
   * route map.
   */
  public function getPathForRouting(AphrontRequest $request) {
    return $request->getPath();
  }

  protected function isHostMatch($host, array $uris) {
    foreach ($uris as $uri) {
      if (!strlen($uri)) {
        continue;
      }

      $domain = id(new PhutilURI($uri))->getDomain();

      if ($domain === $host) {
        return true;
      }
    }

    return false;
  }

  protected function isPathPrefixMatch($path, array $paths) {
    foreach ($paths as $candidate) {
      if (strncmp($path, $candidate, strlen($candidate)) === 0) {
        return true;
      }
    }

    return false;
  }

  final public static function getAllSites() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setSortMethod('getPriority')
      ->execute();
  }

}
