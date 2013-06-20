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

}
