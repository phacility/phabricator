<?php

/**
 * Defines Phabricator's static resources.
 */
final class PhameCelerityResources extends CelerityResources {

  private $skin;

  public function setSkin($skin) {
    $this->skin = $skin;
    return $this;
  }

  public function getSkin() {
    return $this->skin;
  }

  public function getName() {
    return 'phame:'.$this->getSkin()->getName();
  }

  public function getResourceData($name) {
    $resource_path = $this->skin->getRootDirectory().DIRECTORY_SEPARATOR.$name;
    return Filesystem::readFile($resource_path);
  }

}
