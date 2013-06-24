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
      "To configure Asana OAuth, create a new application here:".
      "\n\n".
      "https://app.asana.com/-/account_api".
      "\n\n".
      "When creating your application, use these settings:".
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

  public static function getAsanaProvider() {
    $providers = self::getAllEnabledProviders();

    foreach ($providers as $provider) {
      if ($provider instanceof PhabricatorAuthProviderOAuthAsana) {
        return $provider;
      }
    }

    return null;
  }

}
