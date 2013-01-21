<?php

final class PhabricatorSetupCheckFacebook extends PhabricatorSetupCheck {

  protected function executeChecks() {
    $fb_auth = PhabricatorEnv::getEnvConfig('facebook.auth-enabled');
    if (!$fb_auth) {
      return;
    }

    if (!PhabricatorEnv::getEnvConfig('facebook.application-id')) {
      $message = pht(
        'You have enabled Facebook authentication, but have not provided a '.
        'Facebook Application ID. Provide one or disable Facebook '.
        'authentication.');

      $this->newIssue('config.facebook.application-id')
        ->setName(pht("Facebook Application ID Not Set"))
        ->setMessage($message)
        ->addPhabricatorConfig('facebook.auth-enabled')
        ->addPhabricatorConfig('facebook.application-id');
    }

    if (!PhabricatorEnv::getEnvConfig('facebook.application-secret')) {
      $message = pht(
        'You have enabled Facebook authentication, but have not provided a '.
        'Facebook Application Secret. Provide one or disable Facebook '.
        'authentication.');

      $this->newIssue('config.facebook.application-secret')
        ->setName(pht("Facebook Application Secret Not Set"))
        ->setMessage($message)
        ->addPhabricatorConfig('facebook.auth-enabled')
        ->addPhabricatorConfig('facebook.application-secret');
    }
  }
}
