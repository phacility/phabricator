<?php

final class PhabricatorAuthProviderOAuthDisqus
  extends PhabricatorAuthProviderOAuth {

  public function getProviderName() {
    return pht('Disqus');
  }

  protected function newOAuthAdapter() {
    return new PhutilAuthAdapterOAuthDisqus();
  }

  protected function getLoginIcon() {
    return 'Disqus';
  }

  public function isEnabled() {
    if ($this->hasProviderConfig()) {
      return parent::isEnabled();
    }

    return parent::isEnabled() &&
           PhabricatorEnv::getEnvConfig('disqus.auth-enabled');
  }

  protected function getOAuthClientID() {
    return PhabricatorEnv::getEnvConfig('disqus.application-id');
  }

  protected function getOAuthClientSecret() {
    $secret = PhabricatorEnv::getEnvConfig('disqus.application-secret');
    if ($secret) {
      return new PhutilOpaqueEnvelope($secret);
    }
    return null;
  }

  public function shouldAllowRegistration() {
    if ($this->hasProviderConfig()) {
      return parent::shouldAllowRegistration();
    }
    return PhabricatorEnv::getEnvConfig('disqus.registration-enabled');
  }

  public function shouldAllowAccountUnlink() {
    if ($this->hasProviderConfig()) {
      return parent::shouldAllowAccountUnlink();
    }

    return !PhabricatorEnv::getEnvConfig('disqus.auth-permanent');
  }

}
