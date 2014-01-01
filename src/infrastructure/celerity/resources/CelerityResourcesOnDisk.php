<?php

/**
 * Defines the location of static resources on disk.
 */
abstract class CelerityResourcesOnDisk extends CelerityResources {

  abstract public function getPathToResources();

  public function getResourceData($name) {
    return Filesystem::readFile($this->getPathToResources().'/'.$name);
  }

  public function findBinaryResources() {
    return $this->findResourcesWithSuffixes($this->getBinaryFileSuffixes());
  }

  public function findTextResources() {
    return $this->findResourcesWithSuffixes($this->getTextFileSuffixes());
  }

  protected function getBinaryFileSuffixes() {
    return array(
      'png',
      'jpg',
      'gif',
      'swf',
    );
  }

  protected function getTextFileSuffixes() {
    return array(
      'js',
      'css',
    );
  }

  private function findResourcesWithSuffixes(array $suffixes) {
    $root = $this->getPathToResources();

    $finder = id(new FileFinder($root))
      ->withType('f')
      ->withFollowSymlinks(true)
      ->setGenerateChecksums(true);

    foreach ($suffixes as $suffix) {
      $finder->withSuffix($suffix);
    }

    $raw_files = $finder->find();

    $results = array();
    foreach ($raw_files as $path => $hash) {
      $readable = Filesystem::readablePath($path, $root);
      $results[$readable] = $hash;
    }

    return $results;
  }

}
