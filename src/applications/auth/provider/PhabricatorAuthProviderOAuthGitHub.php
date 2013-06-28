<?php

final class PhabricatorAuthProviderOAuthGitHub
  extends PhabricatorAuthProviderOAuth {

  public function getProviderName() {
    return pht('GitHub');
  }

  public function getConfigurationHelp() {
    $uri = PhabricatorEnv::getProductionURI('/');
    $callback_uri = $this->getLoginURI();

    return pht(
      "To configure GitHub OAuth, create a new GitHub Application here:".
      "\n\n".
      "https://github.com/settings/applications/new".
      "\n\n".
      "You should use these settings in your application:".
      "\n\n".
      "  - **URL:** Set this to your full domain with protocol. For this ".
      "    Phabricator install, the correct value is: `%s`\n".
      "  - **Callback URL**: Set this to: `%s`\n".
      "\n\n".
      "Once you've created an application, copy the **Client ID** and ".
      "**Client Secret** into the fields above.",
      $uri,
      $callback_uri);
  }

  protected function newOAuthAdapter() {
    return new PhutilAuthAdapterOAuthGitHub();
  }

  protected function getLoginIcon() {
    return 'Github';
  }

  public function getLoginURI() {
    // TODO: Clean this up. See PhabricatorAuthOldOAuthRedirectController.
    return PhabricatorEnv::getURI('/oauth/github/login/');
  }

}
