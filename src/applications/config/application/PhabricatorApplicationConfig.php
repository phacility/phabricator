<?php

final class PhabricatorApplicationConfig extends PhabricatorApplication {

  public function getBaseURI() {
    return '/config/';
  }

  public function getIconName() {
    return 'config';
  }

  public function getTitleGlyph() {
    return "\xE2\x98\xBA";
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function getRoutes() {
    return array(
      '/config/' => array(
        ''                          => 'PhabricatorConfigListController',
        'edit/(?P<key>[\w\.\-]+)/'  => 'PhabricatorConfigEditController',
        'issue/' => array(
          '' => 'PhabricatorConfigIssueListController',
          '(?P<key>[^/]+)/' => 'PhabricatorConfigIssueViewController',
        ),
      ),
    );
  }

}
