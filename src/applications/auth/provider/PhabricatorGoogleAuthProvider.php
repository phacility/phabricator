<?php

final class PhabricatorGoogleAuthProvider
  extends PhabricatorOAuth2AuthProvider {

  public function getProviderName() {
    return pht('Google');
  }

  protected function getProviderConfigurationHelp() {
    $login_uri = PhabricatorEnv::getURI($this->getLoginURI());

    return pht(
      "To configure Google OAuth, create a new 'API Project' here:".
      "\n\n".
      "https://console.developers.google.com/".
      "\n\n".
      "Adjust these configuration settings for your project:".
      "\n\n".
      "  - Under **APIs & auth > APIs**, scroll down the list and enable ".
      "    the **Google+ API**.\n".
      "     - You will need to consent to the **Google+ API** terms if you ".
      " have not before.\n".
      "  - Under **APIs & auth > Credentials**, click **Create New Client".
      "    ID** in the **OAuth** section. Then use these settings:\n".
      "     - **Application Type**: Web Application\n".
      "     - **Authorized Javascript origins**: Leave this empty.\n".
      "     - **Authorized redirect URI**: Set this to `%s`.\n".
      "\n\n".
      "After completing configuration, copy the **Client ID** and ".
      "**Client Secret** from the Google console to the fields above.",
      $login_uri);
  }

  protected function newOAuthAdapter() {
    return new PhutilGoogleAuthAdapter();
  }

  protected function getLoginIcon() {
    return 'Google';
  }

  public function getLoginURI() {
    // TODO: Clean this up. See PhabricatorAuthOldOAuthRedirectController.
    return '/oauth/google/login/';
  }

}
