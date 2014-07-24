<?php

final class PhabricatorDoorkeeperApplication extends PhabricatorApplication {

  public function canUninstall() {
    return false;
  }

  public function isLaunchable() {
    return false;
  }

  public function getName() {
    return pht('Doorkeeper');
  }

  public function getShortDescription() {
    return pht('Connect to Other Software');
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
