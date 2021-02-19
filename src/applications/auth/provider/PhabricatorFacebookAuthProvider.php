<?php

final class PhabricatorFacebookAuthProvider
  extends PhabricatorOAuth2AuthProvider {

  public function getProviderName() {
    return pht('Facebook');
  }

  protected function getProviderConfigurationHelp() {
    $uri = PhabricatorEnv::getProductionURI($this->getLoginURI());

    $domain = id(new PhutilURI($uri))->getDomain();

    $table = array(
      'Client OAuth Login' => pht('No'),
      'Web OAuth Login' => pht('Yes'),
      'Enforce HTTPS' => pht('Yes'),
      'Force Web OAuth Reauthentication' => pht('Yes (Optional)'),
      'Embedded Browser OAuth Login' => pht('No'),
      'Use Strict Mode for Redirect URIs' => pht('Yes'),
      'Login from Devices' => pht('No'),
      'Valid OAuth Redirect URIs' => '`'.(string)$uri.'`',
      'App Domains' => '`'.$domain.'`',
    );

    $rows = array();
    foreach ($table as $k => $v) {
      $rows[] = sprintf('| %s | %s |', $k, $v);
      $rows[] = sprintf('|----|    |');
    }
    $rows = implode("\n", $rows);


    return pht(
      'To configure Facebook OAuth, create a new Facebook Application here:'.
      "\n\n".
      'https://developers.facebook.com/apps'.
      "\n\n".
      'You should use these settings in your application:'.
      "\n\n".
      "%s\n".
      "\n\n".
      "After creating your new application, copy the **App ID** and ".
      "**App Secret** to the fields above.",
      $rows);
  }

  protected function newOAuthAdapter() {
    return new PhutilFacebookAuthAdapter();
  }

  protected function getLoginIcon() {
    return 'Facebook';
  }

  protected function getContentSecurityPolicyFormActions() {
    return array(
      // See T13254. After login with a mobile device, Facebook may redirect
      // to the mobile site.
      'https://m.facebook.com/',
    );
  }

}
