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

  public function getLoginURI() {
    // TODO: Clean this up. See PhabricatorAuthOldOAuthRedirectController.
    return PhabricatorEnv::getURI('/oauth/google/login/');
  }

}
