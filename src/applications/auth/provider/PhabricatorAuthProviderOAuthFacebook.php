<?php

final class PhabricatorAuthProviderOAuthFacebook
  extends PhabricatorAuthProviderOAuth {

  public function getProviderName() {
    return pht('Facebook');
  }

  protected function newOAuthAdapter() {
    return new PhutilAuthAdapterOAuthFacebook();
  }

  protected function getLoginIcon() {
    return 'Facebook';
  }

  public function isEnabled() {
    return parent::isEnabled() &&
           PhabricatorEnv::getEnvConfig('facebook.auth-enabled');
  }

  protected function getOAuthClientID() {
    return PhabricatorEnv::getEnvConfig('facebook.application-id');
  }

  protected function getOAuthClientSecret() {
    $secret = PhabricatorEnv::getEnvConfig('facebook.application-secret');
    if ($secret) {
      return new PhutilOpaqueEnvelope($secret);
    }
    return null;
  }

  public function shouldAllowLogin() {
    return true;
  }

  public function shouldAllowRegistration() {
    return PhabricatorEnv::getEnvConfig('facebook.registration-enabled');
  }

  public function shouldAllowAccountLink() {
    return true;
  }

  public function shouldAllowAccountUnlink() {
    return !PhabricatorEnv::getEnvConfig('facebook.auth-permanent');
  }

}
