<?php

final class PhabricatorBMOAuthProvider
  extends PhabricatorOAuth2AuthProvider {

  public function getProviderName() {
    return pht('BMO');
  }

  protected function getProviderConfigurationHelp() {
    $uri = PhabricatorEnv::getProductionURI('/');
    $callback_uri = PhabricatorEnv::getURI($this->getLoginURI());

    return pht(
      "To configure BMO OAuth, create a new BMO OAuth Application here:".
      "\n\n".
      PhabricatorEnv::getEnvConfig('bugzilla.url') . "/admin/oauth/create".
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
    return new PhutilBMOAuthAdapter();
  }

  protected function renderLoginForm(AphrontRequest $request, $mode) {
    require_celerity_resource('bmo-auth-css');
    require_celerity_resource('bmo-auth-js');
    return parent::renderLoginForm($request, $mode);
  }

  public function processLoginRequest(
    PhabricatorAuthLoginController $controller) {
    $adapter = $this->getAdapter();

    list($account, $response) = parent::processLoginRequest($controller);

    // If mfa is disabled in Bugzilla, do not allow login
    if (PhabricatorEnv::getEnvConfig('bugzilla.require_mfa') && !$adapter->getAccountMFA()) {
      $bugzilla_url = PhabricatorEnv::getEnvConfig('bugzilla.url') . '/userprefs.cgi?tab=mfa';
      $error_content = phutil_safe_html(
          'Login using Bugzilla requires multi-factor authentication ' .
          'to be enabled in Bugzilla. Please enable multi-factor authentication ' .
          'in your Bugzilla ' . phutil_tag('a', array('href' => $bugzilla_url), pht('preferences')) .
          ' and try again.');
      $response = $controller->buildProviderErrorResponse($this, $error_content);
      return array(null, $response);
    }

    return array($account, $response);
  }

}
