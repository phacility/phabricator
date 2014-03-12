<?php

final class PhabricatorAuthOldOAuthRedirectController
  extends PhabricatorAuthController {

  private $provider;

  public function shouldRequireLogin() {
    return false;
  }

  public function shouldAllowRestrictedParameter($parameter_name) {
    if ($parameter_name == 'code') {
      return true;
    }
    return parent::shouldAllowRestrictedParameter($parameter_name);
  }

  public function willProcessRequest(array $data) {
    $this->provider = $data['provider'];
  }

  public function processRequest() {
    // TODO: Most OAuth providers are OK with changing the redirect URI, but
    // Google and GitHub are strict. We need to respect the old OAuth URI until
    // we can get installs to migrate. This just keeps the old OAuth URI working
    // by redirecting to the new one.

    $provider_map = array(
      'google' => 'google:google.com',
      'github' => 'github:github.com',
    );

    if (!isset($provider_map[$this->provider])) {
      return new Aphront404Response();
    }

    $provider_key = $provider_map[$this->provider];

    $uri = $this->getRequest()->getRequestURI();
    $uri->setPath($this->getApplicationURI('login/'.$provider_key.'/'));
    return id(new AphrontRedirectResponse())->setURI($uri);
  }

}
