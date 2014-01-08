<?php

/**
 * Defines Phabricator's static resources.
 */
final class CelerityPhabricatorResources extends CelerityResourcesOnDisk {

  public function getName() {
    return 'phabricator';
  }

  public function getPathToResources() {
    return $this->getPhabricatorPath('webroot/');
  }

  public function getPathToMap() {
    return $this->getPhabricatorPath('resources/celerity/map.php');
  }

  private function getPhabricatorPath($to_file) {
    return dirname(phutil_get_library_root('phabricator')).'/'.$to_file;
  }

  public function getResourcePackages() {
    return include $this->getPhabricatorPath('resources/celerity/packages.php');
  }

}
