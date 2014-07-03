<?php

final class PhabricatorAuthProviderOAuth1Bitbucket
  extends PhabricatorAuthProviderOAuth1 {

  public function getProviderName() {
    return pht('Bitbucket');
  }

  public function getProviderConfigurationHelp() {
    return pht(
      "To configure Bitbucket OAuth, log in to Bitbucket and go to ".
      "**Manage Account** > **Access Management** > **OAuth**.\n\n".
      "Click **Add Consumer** and create a new application.\n\n".
      "After completing configuration, copy the **Key** and ".
      "**Secret** to the fields above.");
  }

  protected function newOAuthAdapter() {
    return new PhutilAuthAdapterOAuthBitbucket();
  }

  protected function getLoginIcon() {
    return 'Bitbucket';
  }

}
