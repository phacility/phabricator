<?php

final class PhabricatorApplicationAuth extends PhabricatorApplication {

  public function canUninstall() {
    return false;
  }

  public function getBaseURI() {
    return '/auth/';
  }

  public function getIconName() {
    return 'authentication';
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {

    $items = array();

    if ($user->isLoggedIn()) {
      $item = id(new PHUIListItemView())
        ->addClass('core-menu-item')
        ->setName(pht('Log Out'))
        ->setIcon('power')
        ->setWorkflow(true)
        ->setHref('/logout/')
        ->setSelected(($controller instanceof PhabricatorLogoutController));
      $items[] = $item;
    }

    return $items;
  }

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function getRoutes() {
    return array(
      '/auth/' => array(
/*

        '(query/(?P<key>[^/]+)/)?' => 'PhabricatorAuthListController',
        'config/' => array(
          'new/' => 'PhabricatorAuthNewController',
          'new/(?P<className>[^/]+)/' => 'PhabricatorAuthEditController',
          'edit/(?P<id>\d+)/' => 'PhabricatorAuthEditController',
          '(?P<action>enable|disable)/(?P<id>\d+)/' =>
            'PhabricatorAuthDisableController',
        ),

*/
        'login/(?P<pkey>[^/]+)/' => 'PhabricatorAuthLoginController',
        'register/(?:(?P<akey>[^/]+)/)?' => 'PhabricatorAuthRegisterController',
        'start/' => 'PhabricatorAuthStartController',
        'validate/' => 'PhabricatorAuthValidateController',
        'unlink/(?P<pkey>[^/]+)/' => 'PhabricatorAuthUnlinkController',
        'link/(?P<pkey>[^/]+)/' => 'PhabricatorAuthLinkController',
        'confirmlink/(?P<akey>[^/]+)/'
          => 'PhabricatorAuthConfirmLinkController',
      ),

      '/login/' => array(
        '' => 'PhabricatorLoginController',
        'email/' => 'PhabricatorEmailLoginController',
        'etoken/(?P<token>\w+)/' => 'PhabricatorEmailTokenController',
        'refresh/' => 'PhabricatorRefreshCSRFController',
        'mustverify/' => 'PhabricatorMustVerifyEmailController',
      ),

      '/logout/' => 'PhabricatorLogoutController',

      '/oauth/' => array(
        '(?P<provider>\w+)/' => array(
          'login/'     => 'PhabricatorOAuthLoginController',
          'diagnose/'  => 'PhabricatorOAuthDiagnosticsController',
        ),
      ),

      '/ldap/' => array(
        'login/' => 'PhabricatorLDAPLoginController',
      ),
    );
  }

}
