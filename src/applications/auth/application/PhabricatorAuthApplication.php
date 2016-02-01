<?php

final class PhabricatorAuthApplication extends PhabricatorApplication {

  public function canUninstall() {
    return false;
  }

  public function getBaseURI() {
    return '/auth/';
  }

  public function getIcon() {
    return 'fa-key';
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

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    // NOTE: Although reasonable help exists for this in "Configuring Accounts
    // and Registration", specifying help items here means we get the menu
    // item in all the login/link interfaces, which is confusing and not
    // helpful.

    // TODO: Special case this, or split the auth and auth administration
    // applications?

    return array();
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
        '(?P<loggedout>loggedout)/' => 'PhabricatorAuthStartController',
        'invite/(?P<code>[^/]+)/' => 'PhabricatorAuthInviteController',
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
        'sshkey/' => array(
          'generate/' => 'PhabricatorAuthSSHKeyGenerateController',
          'upload/' => 'PhabricatorAuthSSHKeyEditController',
          'edit/(?P<id>\d+)/' => 'PhabricatorAuthSSHKeyEditController',
          'delete/(?P<id>\d+)/' => 'PhabricatorAuthSSHKeyDeleteController',
        ),
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

  protected function getCustomCapabilities() {
    return array(
      AuthManageProvidersCapability::CAPABILITY => array(
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }
}
