<?php

final class PhabricatorAmazonAuthProvider
  extends PhabricatorOAuth2AuthProvider {

  public function getProviderName() {
    return pht('Amazon');
  }

  protected function getProviderConfigurationHelp() {
    $login_uri = PhabricatorEnv::getURI($this->getLoginURI());

    $uri = new PhutilURI(PhabricatorEnv::getProductionURI('/'));
    $https_note = null;
    if ($uri->getProtocol() !== 'https') {
      $https_note = pht(
        'NOTE: Amazon **requires** HTTPS, but your Phabricator install does '.
        'not use HTTPS. **You will not be able to add Amazon as an '.
        'authentication provider until you configure HTTPS on this install**.');
    }

    return pht(
      "%s\n\n".
      "To configure Amazon OAuth, create a new 'API Project' here:".
      "\n\n".
      "http://login.amazon.com/manageApps".
      "\n\n".
      "Use these settings:".
      "\n\n".
      "  - **Allowed Return URLs:** Add this: `%s`".
      "\n\n".
      "After completing configuration, copy the **Client ID** and ".
      "**Client Secret** to the fields above.",
      $https_note,
      $login_uri);
  }

  protected function newOAuthAdapter() {
    return new PhutilAmazonAuthAdapter();
  }

  protected function getLoginIcon() {
    return 'Amazon';
  }

}
