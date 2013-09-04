<?php

final class PhabricatorApplicationDoorkeeper extends PhabricatorApplication {

  public function canUninstall() {
    return false;
  }

  public function getBaseURI() {
    return '/doorkeeper/';
  }

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function getRemarkupRules() {
    return array(
      new DoorkeeperRemarkupRuleAsana(),
      new DoorkeeperRemarkupRuleJIRA(),
    );
  }

  public function getRoutes() {
    return array(
      '/doorkeeper/' => array(
        'tags/' => 'DoorkeeperTagsController',
      ),
    );
  }

}
