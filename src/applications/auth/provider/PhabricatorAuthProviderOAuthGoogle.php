<?php

final class PhabricatorAuthProviderOAuthGoogle
  extends PhabricatorAuthProviderOAuth {

  public function getProviderName() {
    return pht('Google');
  }

  protected function newOAuthAdapter() {
    return new PhutilAuthAdapterOAuthGoogle();
  }

  protected function getLoginIcon() {
    return 'Google';
  }

  public function isEnabled() {
    return parent::isEnabled() &&
           PhabricatorEnv::getEnvConfig('google.auth-enabled');
  }

  protected function getOAuthClientID() {
    return PhabricatorEnv::getEnvConfig('google.application-id');
  }

  protected function getOAuthClientSecret() {
    $secret = PhabricatorEnv::getEnvConfig('google.application-secret');
    if ($secret) {
      return new PhutilOpaqueEnvelope($secret);
    }
    return null;
  }

  public function shouldAllowLogin() {
    return true;
  }

  public function shouldAllowRegistration() {
    return PhabricatorEnv::getEnvConfig('google.registration-enabled');
  }

  public function shouldAllowAccountLink() {
    return true;
  }

  public function shouldAllowAccountUnlink() {
    return !PhabricatorEnv::getEnvConfig('google.auth-permanent');
  }

}
