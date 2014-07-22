<?php

final class PhabricatorDisqusAuthProvider
  extends PhabricatorOAuth2AuthProvider {

  public function getProviderName() {
    return pht('Disqus');
  }

  protected function getProviderConfigurationHelp() {
    $login_uri = PhabricatorEnv::getURI($this->getLoginURI());

    return pht(
      "To configure Disqus OAuth, create a new application here:".
      "\n\n".
      "http://disqus.com/api/applications/".
      "\n\n".
      "Create an application, then adjust these settings:".
      "\n\n".
      "  - **Callback URL:** Set this to `%s`".
      "\n\n".
      "After creating an application, copy the **Public Key** and ".
      "**Secret Key** to the fields above (the **Public Key** goes in ".
      "**OAuth App ID**).",
      $login_uri);
  }

  protected function newOAuthAdapter() {
    return new PhutilDisqusAuthAdapter();
  }

  protected function getLoginIcon() {
    return 'Disqus';
  }

}
