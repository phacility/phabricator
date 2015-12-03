<?php

final class DivinerPublishCache extends DivinerDiskCache {

  private $pathMap;
  private $index;

  public function __construct($cache_directory) {
    parent::__construct($cache_directory, 'diviner-publish-cache');
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


/* -(  Index  )-------------------------------------------------------------- */


  public function getIndex() {
    if ($this->index === null) {
      $this->index = $this->getCache()->getKey('index', array());
    }
    return $this->index;
  }

  public function writeIndex() {
    $this->getCache()->setKey('index', $this->getIndex());
  }

  public function deleteAtomFromIndex($hash) {
    $index = $this->getIndex();
    unset($index[$hash]);
    $this->index = $index;
    return $this;
  }

  public function addAtomToIndex($hash, array $data) {
    $index = $this->getIndex();
    $index[$hash] = $data;
    $this->index = $index;
    return $this;
  }

}
