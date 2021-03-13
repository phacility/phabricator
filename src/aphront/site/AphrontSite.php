<?php

abstract class AphrontSite extends Phobject {

  abstract public function getPriority();
  abstract public function getDescription();

  abstract public function shouldRequireHTTPS();
  abstract public function newSiteForRequest(AphrontRequest $request);
  abstract public function getRoutingMaps();

  public function new404Controller(AphrontRequest $request) {
    return new Phabricator404Controller();
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

  protected function newRoutingMap() {
    return id(new AphrontRoutingMap())
      ->setSite($this);
  }

  final public static function getAllSites() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setSortMethod('getPriority')
      ->execute();
  }

}
