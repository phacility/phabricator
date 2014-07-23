<?php

final class PhabricatorConfigApplication extends PhabricatorApplication {

  public function getBaseURI() {
    return '/config/';
  }

  public function getIconName() {
    return 'setup';
  }

  public function isPinnedByDefault(PhabricatorUser $viewer) {
    return $viewer->getIsAdmin();
  }

  public function getTitleGlyph() {
    return "\xE2\x98\xBA";
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function canUninstall() {
    return false;
  }

  public function getName() {
    return 'Config';
  }

  public function getShortDescription() {
    return pht('Configure Phabricator');
  }

  public function getRoutes() {
    return array(
      '/config/' => array(
        '' => 'PhabricatorConfigListController',
        'all/' => 'PhabricatorConfigAllController',
        'edit/(?P<key>[\w\.\-]+)/' => 'PhabricatorConfigEditController',
        'group/(?P<key>[^/]+)/' => 'PhabricatorConfigGroupController',
        'welcome/' => 'PhabricatorConfigWelcomeController',
        '(?P<verb>ignore|unignore)/(?P<key>[^/]+)/'
          => 'PhabricatorConfigIgnoreController',
        'issue/' => array(
          '' => 'PhabricatorConfigIssueListController',
          '(?P<key>[^/]+)/' => 'PhabricatorConfigIssueViewController',
        ),
      ),
    );
  }

}
