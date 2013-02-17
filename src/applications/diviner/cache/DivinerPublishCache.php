<?php

final class DivinerPublishCache extends DivinerDiskCache {

  private $pathMap;

  public function __construct($cache_directory) {
    return parent::__construct($cache_directory, 'diviner-publish-cache');
  }


/* -(  Path Map  )----------------------------------------------------------- */


  public function getPathMap() {
    if ($this->pathMap === null) {
      $this->pathMap = $this->getCache()->getKey('path', array());
    }
    return $this->pathMap;
  }

  public function writePathMap() {
    $this->getCache()->setKey('path', $this->getPathMap());
  }

  public function getAtomPathsFromCache($hash) {
    return idx($this->getPathMap(), $hash, array());
  }

  public function removeAtomPathsFromCache($hash) {
    $map = $this->getPathMap();
    unset($map[$hash]);
    $this->pathMap = $map;
    return $this;
  }

  public function addAtomPathsToCache($hash, array $paths) {
    $map = $this->getPathMap();
    $map[$hash] = $paths;
    $this->pathMap = $map;
    return $this;
  }


}
