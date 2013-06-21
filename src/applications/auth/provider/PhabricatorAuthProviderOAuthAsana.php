<?php

final class PhabricatorAuthProviderOAuthAsana
  extends PhabricatorAuthProviderOAuth {

  public function getProviderName() {
    return pht('Asana');
  }

  public function getConfigurationHelp() {
    $app_uri = PhabricatorEnv::getProductionURI('/');
    $login_uri = $this->getLoginURI();

    return pht(
      "To configure Asana OAuth, create a new application by logging in to ".
      "Asana and going to **Account Settings**, then **Apps**, then ".
      "**Add New Application**.".
      "\n\n".
      "Use these settings:".
      "\n\n".
      "  - **App URL:** Set this to: `%s`\n".
      "  - **Redirect URL:** Set this to: `%s`".
      "\n\n".
      "After completing configuration, copy the **Client ID** and ".
      "**Client Secret** to the fields above.",
      $app_uri,
      $login_uri);
  }

  protected function newOAuthAdapter() {
    return new PhutilAuthAdapterOAuthAsana();
  }

  protected function getLoginIcon() {
    return 'Asana';
  }

}
