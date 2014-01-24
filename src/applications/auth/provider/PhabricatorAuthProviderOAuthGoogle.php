<?php

final class PhabricatorAuthProviderOAuthGoogle
  extends PhabricatorAuthProviderOAuth {

  public function getProviderName() {
    return pht('Google');
  }

  public function getConfigurationHelp() {
    $login_uri = PhabricatorEnv::getURI($this->getLoginURI());

    return pht(
      "To configure Google OAuth, create a new 'API Project' here:".
      "\n\n".
      "https://code.google.com/apis/console/".
      "\n\n".
      "You don't need to enable any Services, just go to **API Access**, ".
      "click **Create an OAuth 2.0 client ID...**, and configure these ".
      "settings:".
      "\n\n".
      "  - During initial setup click **More Options** (or after creating ".
      "    the client ID, click **Edit Settings...**), then add this to ".
      "    **Authorized Redirect URIs**: `%s`\n".
      "\n\n".
      "After completing configuration, copy the **Client ID** and ".
      "**Client Secret** to the fields above.",
      $login_uri);
  }

  protected function newOAuthAdapter() {
    return new PhutilAuthAdapterOAuthGoogle();
  }

  protected function getLoginIcon() {
    return 'Google';
  }

  public function getLoginURI() {
    // TODO: Clean this up. See PhabricatorAuthOldOAuthRedirectController.
    return '/oauth/google/login/';
  }

}
