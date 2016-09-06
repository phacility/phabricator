<?php

final class PhabricatorSlackAuthProvider
  extends PhabricatorOAuth2AuthProvider {

  public function getProviderName() {
    return pht('Slack');
  }

  protected function getProviderConfigurationHelp() {
    $login_uri = PhabricatorEnv::getURI($this->getLoginURI());

    return pht(
      "To configure Slack OAuth, create a new application here:".
      "\n\n".
      "https://api.slack.com/docs/sign-in-with-slack#create_slack_app".
      "\n\n".
      "When creating your application, use these settings:".
      "\n\n".
      "  - **Redirect URI:** Set this to: `%s`".
      "\n\n".
      "After completing configuration, copy the **Client ID** and ".
      "**Client Secret** to the fields above. (You may need to generate the ".
      "client secret by clicking 'New Secret' first.)",
      $login_uri);
  }

  protected function newOAuthAdapter() {
    return new PhutilSlackAuthAdapter();
  }

  protected function getLoginIcon() {
    return 'Slack';
  }

}
