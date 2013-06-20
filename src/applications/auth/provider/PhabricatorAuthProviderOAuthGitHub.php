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

}
