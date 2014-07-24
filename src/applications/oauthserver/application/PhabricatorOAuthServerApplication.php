<?php

final class PhabricatorOAuthServerApplication extends PhabricatorApplication {

  public function getName() {
    return pht('OAuth Server');
  }

  public function getBaseURI() {
    return '/oauthserver/';
  }

  public function getShortDescription() {
    return pht('OAuth Login Provider');
  }

  public function getIconName() {
    return 'oauthserver';
  }

  public function getTitleGlyph() {
    return "\xE2\x99\x86";
  }

  public function getFlavorText() {
    return pht('Login with Phabricator');
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function isBeta() {
    return true;
  }

  public function getHelpURI() {
    return PhabricatorEnv::getDoclink('Using the Phabricator OAuth Server');
  }

  public function getRoutes() {
    return array(
      '/oauthserver/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorOAuthClientListController',
        'auth/' => 'PhabricatorOAuthServerAuthController',
        'test/(?P<id>\d+)/' => 'PhabricatorOAuthServerTestController',
        'token/' => 'PhabricatorOAuthServerTokenController',
        'client/' => array(
          'create/' => 'PhabricatorOAuthClientEditController',
          'delete/(?P<phid>[^/]+)/' => 'PhabricatorOAuthClientDeleteController',
          'edit/(?P<phid>[^/]+)/' => 'PhabricatorOAuthClientEditController',
          'view/(?P<phid>[^/]+)/' => 'PhabricatorOAuthClientViewController',
        ),
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      PhabricatorOAuthServerCreateClientsCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }

}
