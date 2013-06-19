<?php

final class PhabricatorAuthOldOAuthRedirectController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    // TODO: Most OAuth providers are OK with changing the redirect URI, but
    // Google is strict. We need to respect the old OAuth URI until we can
    // get installs to migrate. This just keeps the old OAuth URI working
    // by redirecting to the new one.

    $uri = $this->getRequest()->getRequestURI();
    $uri->setPath($this->getApplicationURI('login/google:google.com/'));
    return id(new AphrontRedirectResponse())->setURI($uri);
  }

}
