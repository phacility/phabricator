<?php

final class PhabricatorAuthProviderOAuthGitHub
  extends PhabricatorAuthProviderOAuth {

  public function getProviderName() {
    return pht('GitHub');
  }

  protected function newOAuthAdapter() {
    return new PhutilAuthAdapterOAuthGitHub();
  }

  protected function getLoginIcon() {
    return 'Github';
  }

  public function isEnabled() {
    if ($this->hasProviderConfig()) {
      return parent::isEnabled();
    }

    return parent::isEnabled() &&
           PhabricatorEnv::getEnvConfig('github.auth-enabled');
  }

  protected function getOAuthClientID() {
    return PhabricatorEnv::getEnvConfig('github.application-id');
  }

  protected function getOAuthClientSecret() {
    $secret = PhabricatorEnv::getEnvConfig('github.application-secret');
    if ($secret) {
      return new PhutilOpaqueEnvelope($secret);
    }
    return null;
  }

  public function shouldAllowRegistration() {
    if ($this->hasProviderConfig()) {
      return parent::shouldAllowRegistration();
    }
    return PhabricatorEnv::getEnvConfig('github.registration-enabled');
  }

  public function shouldAllowAccountUnlink() {
    if ($this->hasProviderConfig()) {
      return parent::shouldAllowAccountUnlink();
    }
    return !PhabricatorEnv::getEnvConfig('github.auth-permanent');
  }

}
