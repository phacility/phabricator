<?php

abstract class PhameSite extends PhabricatorSite {

  protected function isPhameActive() {
    $base_uri = PhabricatorEnv::getEnvConfig('phabricator.base-uri');
    if (!strlen($base_uri)) {
      // Don't activate Phame if we don't have a base URI configured.
      return false;
    }

    $phame_installed = PhabricatorApplication::isClassInstalled(
      'PhabricatorPhameApplication');
    if (!$phame_installed) {
      // Don't activate Phame if the the application is uninstalled.
      return false;
    }

    return true;
  }

}
