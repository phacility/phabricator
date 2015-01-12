<?php

final class PhabricatorBitbucketAuthProvider
  extends PhabricatorOAuth1AuthProvider {

  public function getProviderName() {
    return pht('Bitbucket');
  }

  protected function getProviderConfigurationHelp() {
    return pht(
      "To configure Bitbucket OAuth, log in to Bitbucket and go to ".
      "**Manage Account** > **Access Management** > **OAuth**.\n\n".
      "Click **Add Consumer** and create a new application.\n\n".
      "After completing configuration, copy the **Key** and ".
      "**Secret** to the fields above.");
  }

  protected function newOAuthAdapter() {
    return new PhutilBitbucketAuthAdapter();
  }

  protected function getLoginIcon() {
    return 'Bitbucket';
  }

}
