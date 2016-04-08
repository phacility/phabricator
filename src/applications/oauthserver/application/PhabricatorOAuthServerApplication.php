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

  public function getIcon() {
    return 'fa-hotel';
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

  public function isPrototype() {
    return true;
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array(
      array(
        'name' => pht('Using the Phabricator OAuth Server'),
        'href' => PhabricatorEnv::getDoclink(
          'Using the Phabricator OAuth Server'),
      ),
    );
  }

  public function getRoutes() {
    return array(
      '/oauthserver/' => array(
        '(?:query/(?P<queryKey>[^/]+)/)?'
          => 'PhabricatorOAuthClientListController',
        'auth/' => 'PhabricatorOAuthServerAuthController',
        'token/' => 'PhabricatorOAuthServerTokenController',
        $this->getEditRoutePattern('edit/') =>
          'PhabricatorOAuthClientEditController',
          'client/' => array(
          'disable/(?P<id>\d+)/' => 'PhabricatorOAuthClientDisableController',
          'view/(?P<id>\d+)/' => 'PhabricatorOAuthClientViewController',
          'secret/(?P<id>\d+)/' => 'PhabricatorOAuthClientSecretController',
          'test/(?P<id>\d+)/' => 'PhabricatorOAuthClientTestController',
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
