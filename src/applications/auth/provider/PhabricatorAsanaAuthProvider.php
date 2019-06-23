<?php

final class PhabricatorAsanaAuthProvider
  extends PhabricatorOAuth2AuthProvider
  implements DoorkeeperRemarkupURIInterface {

  public function getProviderName() {
    return pht('Asana');
  }

  protected function getProviderConfigurationHelp() {
    $app_uri = PhabricatorEnv::getProductionURI('/');
    $login_uri = PhabricatorEnv::getURI($this->getLoginURI());

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
    return new PhutilAsanaAuthAdapter();
  }

  protected function getLoginIcon() {
    return 'Asana';
  }

  public static function getAsanaProvider() {
    $providers = self::getAllEnabledProviders();

    foreach ($providers as $provider) {
      if ($provider instanceof PhabricatorAsanaAuthProvider) {
        return $provider;
      }
    }

    return null;
  }

/* -(  DoorkeeperRemarkupURIInterface  )------------------------------------- */

  public function getDoorkeeperURIRef(PhutilURI $uri) {
    $uri_string = phutil_string_cast($uri);

    $pattern = '(https://app\\.asana\\.com/0/(\\d+)/(\\d+))';
    $matches = null;
    if (!preg_match($pattern, $uri_string, $matches)) {
      return null;
    }

    if (strlen($uri->getFragment())) {
     return null;
    }

    if ($uri->getQueryParamsAsPairList()) {
     return null;
    }

    $context_id = $matches[1];
    $task_id = $matches[2];

    return id(new DoorkeeperURIRef())
      ->setURI($uri)
      ->setApplicationType(DoorkeeperBridgeAsana::APPTYPE_ASANA)
      ->setApplicationDomain(DoorkeeperBridgeAsana::APPDOMAIN_ASANA)
      ->setObjectType(DoorkeeperBridgeAsana::OBJTYPE_TASK)
      ->setObjectID($task_id);
  }

}
