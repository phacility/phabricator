<?php

abstract class PhabricatorAuthContactNumberController
  extends PhabricatorAuthController {

  // Users may need to access these controllers to enroll in SMS MFA during
  // account setup.

  public function shouldRequireMultiFactorEnrollment() {
    return false;
  }

  public function shouldRequireEnabledUser() {
    return false;
  }

  public function shouldRequireEmailVerification() {
    return false;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addTextCrumb(
      pht('Contact Numbers'),
      pht('/settings/panel/contact/'));

    return $crumbs;
  }

}
