<?php

final class PhabricatorApplicationOAuthServer extends PhabricatorApplication {

  public function getBaseURI() {
    return '/oauthserver/';
  }

  public function getShortDescription() {
    return pht('OAuth Provider');
  }

  public function getIconName() {
    return 'oauthserver';
  }

  public function getTitleGlyph() {
    return "~";
  }

  public function getFlavorText() {
    return pht('yerps');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function canUninstall() {
    return false;
  }

  public function isBeta() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/oauthserver/' => array(
        '' => 'PhabricatorOAuthServerConsoleController',
        'auth/'          => 'PhabricatorOAuthServerAuthController',
        'test/'          => 'PhabricatorOAuthServerTestController',
        'token/'         => 'PhabricatorOAuthServerTokenController',
        'clientauthorization/' => array(
          '' => 'PhabricatorOAuthClientAuthorizationListController',
          'delete/(?P<phid>[^/]+)/' =>
            'PhabricatorOAuthClientAuthorizationDeleteController',
          'edit/(?P<phid>[^/]+)/' =>
            'PhabricatorOAuthClientAuthorizationEditController',
        ),
        'client/' => array(
          ''                        => 'PhabricatorOAuthClientListController',
          'create/'                 => 'PhabricatorOAuthClientEditController',
          'delete/(?P<phid>[^/]+)/' => 'PhabricatorOAuthClientDeleteController',
          'edit/(?P<phid>[^/]+)/'   => 'PhabricatorOAuthClientEditController',
          'view/(?P<phid>[^/]+)/'   => 'PhabricatorOAuthClientViewController',
        ),
      ),
    );
  }

}
