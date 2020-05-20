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
          'edit/(?:(?P<id>\d+)/)?' => 'PhabricatorAuthEditController',
          '(?P<action>enable|disable)/(?P<id>\d+)/'
            => 'PhabricatorAuthDisableController',
          'view/(?P<id>\d+)/' => 'PhabricatorAuthProviderViewController',
        ),
        'login/(?P<pkey>[^/]+)/(?:(?P<extra>[^/]+)/)?'
          => 'PhabricatorAuthLoginController',
        '(?P<loggedout>loggedout)/' => 'PhabricatorAuthStartController',
        'invite/(?P<code>[^/]+)/' => 'PhabricatorAuthInviteController',
        'register/(?:(?P<akey>[^/]+)/)?' => 'PhabricatorAuthRegisterController',
        'start/' => 'PhabricatorAuthStartController',
        'validate/' => 'PhabricatorAuthValidateController',
        'finish/' => 'PhabricatorAuthFinishController',
        'unlink/(?P<id>\d+)/' => 'PhabricatorAuthUnlinkController',
        '(?P<action>link|refresh)/(?P<id>\d+)/'
          => 'PhabricatorAuthLinkController',
        'confirmlink/(?P<akey>[^/]+)/'
          => 'PhabricatorAuthConfirmLinkController',
        'session/terminate/(?P<id>[^/]+)/'
          => 'PhabricatorAuthTerminateSessionController',
        'token/revoke/(?P<id>[^/]+)/'
          => 'PhabricatorAuthRevokeTokenController',
        'session/downgrade/'
          => 'PhabricatorAuthDowngradeSessionController',
        'enroll/' => array(
          '(?:(?P<pageKey>[^/]+)/)?'
            => 'PhabricatorAuthNeedsMultiFactorController',
        ),
        'sshkey/' => array(
          $this->getQueryRoutePattern('for/(?P<forPHID>[^/]+)/')
            => 'PhabricatorAuthSSHKeyListController',
          'generate/' => 'PhabricatorAuthSSHKeyGenerateController',
          'upload/' => 'PhabricatorAuthSSHKeyEditController',
          'edit/(?P<id>\d+)/' => 'PhabricatorAuthSSHKeyEditController',
          'revoke/(?P<id>\d+)/'
            => 'PhabricatorAuthSSHKeyRevokeController',
          'view/(?P<id>\d+)/' => 'PhabricatorAuthSSHKeyViewController',
        ),

        'password/' => 'PhabricatorAuthSetPasswordController',
        'external/' => 'PhabricatorAuthSetExternalController',

        'mfa/' => array(
          $this->getQueryRoutePattern() =>
            'PhabricatorAuthFactorProviderListController',
          $this->getEditRoutePattern('edit/') =>
            'PhabricatorAuthFactorProviderEditController',
          '(?P<id>[1-9]\d*)/' =>
            'PhabricatorAuthFactorProviderViewController',
          'message/(?P<id>[1-9]\d*)/' =>
            'PhabricatorAuthFactorProviderMessageController',
          'challenge/status/(?P<id>[1-9]\d*)/' =>
            'PhabricatorAuthChallengeStatusController',
        ),

        'message/' => array(
          $this->getQueryRoutePattern() =>
            'PhabricatorAuthMessageListController',
          $this->getEditRoutePattern('edit/') =>
            'PhabricatorAuthMessageEditController',
          '(?P<id>[^/]+)/' =>
            'PhabricatorAuthMessageViewController',
        ),

        'contact/' => array(
          $this->getEditRoutePattern('edit/') =>
            'PhabricatorAuthContactNumberEditController',
          '(?P<id>[1-9]\d*)/' =>
            'PhabricatorAuthContactNumberViewController',
          '(?P<action>disable|enable)/(?P<id>[1-9]\d*)/' =>
            'PhabricatorAuthContactNumberDisableController',
          'primary/(?P<id>[1-9]\d*)/' =>
            'PhabricatorAuthContactNumberPrimaryController',
          'test/(?P<id>[1-9]\d*)/' =>
            'PhabricatorAuthContactNumberTestController',
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
