<?php

final class PhabricatorAuthApplication extends PhabricatorApplication {

  public function canUninstall() {
    return false;
  }

  public function getBaseURI() {
    return '/auth/';
  }

  public function getIconName() {
    return 'authentication';
  }

  public function isPinnedByDefault(PhabricatorUser $viewer) {
    return $viewer->getIsAdmin();
  }

  public function getName() {
    return pht('Auth');
  }

  public function getShortDescription() {
    return pht('Login/Registration');
  }

  public function getHelpURI() {
    // NOTE: Although reasonable help exists for this in "Configuring Accounts
    // and Registration", specifying a help URI here means we get the menu
    // item in all the login/link interfaces, which is confusing and not
    // helpful.

    // TODO: Special case this, or split the auth and auth administration
    // applications?

    return null;
  }

  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {

    $items = array();

    if ($user->isLoggedIn()) {
      $item = id(new PHUIListItemView())
        ->addClass('core-menu-item')
        ->setName(pht('Log Out'))
        ->setIcon('logout-sm')
        ->setWorkflow(true)
        ->setHref('/logout/')
        ->setSelected(($controller instanceof PhabricatorLogoutController))
        ->setAural(pht('Log Out'))
        ->setOrder(900);
      $items[] = $item;
    } else {
      if ($controller instanceof PhabricatorAuthController) {
        // Don't show the "Login" item on auth controllers, since they're
        // generally all related to logging in anyway.
      } else {
        $item = id(new PHUIListItemView())
          ->addClass('core-menu-item')
          ->setName(pht('Log In'))
          // TODO: Login icon?
          ->setIcon('power')
          ->setHref('/auth/start/')
          ->setAural(pht('Log In'))
          ->setOrder(900);
        $items[] = $item;
      }
    }

    return $items;
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function getRoutes() {
    return array(
      '/auth/' => array(
        '' => 'PhabricatorAuthListController',
        'config/' => array(
          'new/' => 'PhabricatorAuthNewController',
          'new/(?P<className>[^/]+)/' => 'PhabricatorAuthEditController',
          'edit/(?P<id>\d+)/' => 'PhabricatorAuthEditController',
          '(?P<action>enable|disable)/(?P<id>\d+)/'
            => 'PhabricatorAuthDisableController',
        ),
        'login/(?P<pkey>[^/]+)/(?:(?P<extra>[^/]+)/)?'
          => 'PhabricatorAuthLoginController',
        'register/(?:(?P<akey>[^/]+)/)?' => 'PhabricatorAuthRegisterController',
        'start/' => 'PhabricatorAuthStartController',
        'validate/' => 'PhabricatorAuthValidateController',
        'finish/' => 'PhabricatorAuthFinishController',
        'unlink/(?P<pkey>[^/]+)/' => 'PhabricatorAuthUnlinkController',
        '(?P<action>link|refresh)/(?P<pkey>[^/]+)/'
          => 'PhabricatorAuthLinkController',
        'confirmlink/(?P<akey>[^/]+)/'
          => 'PhabricatorAuthConfirmLinkController',
        'session/terminate/(?P<id>[^/]+)/'
          => 'PhabricatorAuthTerminateSessionController',
        'token/revoke/(?P<id>[^/]+)/'
          => 'PhabricatorAuthRevokeTokenController',
        'session/downgrade/'
          => 'PhabricatorAuthDowngradeSessionController',
        'multifactor/'
          => 'PhabricatorAuthNeedsMultiFactorController',
      ),

      '/oauth/(?P<provider>\w+)/login/'
        => 'PhabricatorAuthOldOAuthRedirectController',

      '/login/' => array(
        '' => 'PhabricatorAuthStartController',
        'email/' => 'PhabricatorEmailLoginController',
        'once/'.
          '(?P<type>[^/]+)/'.
          '(?P<id>\d+)/'.
          '(?P<key>[^/]+)/'.
          '(?:(?P<emailID>\d+)/)?' => 'PhabricatorAuthOneTimeLoginController',
        'refresh/' => 'PhabricatorRefreshCSRFController',
        'mustverify/' => 'PhabricatorMustVerifyEmailController',
      ),

      '/emailverify/(?P<code>[^/]+)/'
        => 'PhabricatorEmailVerificationController',

      '/logout/' => 'PhabricatorLogoutController',
    );
  }

}
