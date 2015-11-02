<?php

final class PhabricatorCelerityApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Celerity');
  }

  public function canUninstall() {
    return false;
  }

  public function isUnlisted() {
    return true;
  }

  public function getRoutes() {
    // We serve resources from both the platform site and the resource site.
    // This is safe because the user doesn't have any direct control over
    // resources.

    // The advantage of serving resources from the resource site (if possible)
    // is that we can use a CDN there if one is configured, but there is no
    // particular security concern.
    return $this->getResourceRoutes();
  }

  public function getResourceRoutes() {
    $extensions = CelerityResourceController::getSupportedResourceTypes();
    $extensions = array_keys($extensions);
    $extensions = implode('|', $extensions);

    return array(
      '/res/' => array(
        '(?:(?P<mtime>[0-9]+)T/)?'.
        '(?:(?P<postprocessor>[^/]+)X/)?'.
        '(?P<library>[^/]+)/'.
        '(?P<hash>[a-f0-9]{8})/'.
        '(?P<path>.+\.(?:'.$extensions.'))'
          => 'CelerityPhabricatorResourceController',
      ),
    );
  }

}
