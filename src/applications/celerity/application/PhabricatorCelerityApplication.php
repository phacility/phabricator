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
    $extensions = CelerityResourceController::getSupportedResourceTypes();
    $extensions = array_keys($extensions);
    $extensions = implode('|', $extensions);

    return array(
      '/res/' => array(
        '(?:(?P<mtime>[0-9]+)T/)?'.
        '(?P<library>[^/]+)/'.
        '(?P<hash>[a-f0-9]{8})/'.
        '(?P<path>.+\.(?:'.$extensions.'))'
          => 'CelerityPhabricatorResourceController',
      ),
    );
  }

}
