<?php

final class PhabricatorAuthProviderOAuthFacebook
  extends PhabricatorAuthProviderOAuth {

  const KEY_REQUIRE_SECURE = 'oauth:facebook:require-secure';

  public function getProviderName() {
    return pht('Facebook');
  }

  public function getDefaultProviderConfig() {
    return parent::getDefaultProviderConfig()
      ->setProperty(self::KEY_REQUIRE_SECURE, 1);
  }

  protected function newOAuthAdapter() {
    $require_secure = $this->getProviderConfig()->getProperty(
      self::KEY_REQUIRE_SECURE);

    return id(new PhutilAuthAdapterOAuthFacebook())
      ->setRequireSecureBrowsing($require_secure);
  }

  protected function getLoginIcon() {
    return 'Facebook';
  }

  public function readFormValuesFromProvider() {
    $require_secure = $this->getProviderConfig()->getProperty(
      self::KEY_REQUIRE_SECURE);

    return parent::readFormValuesFromProvider() + array(
      self::KEY_REQUIRE_SECURE => $require_secure,
    );
  }

  public function readFormValuesFromRequest(AphrontRequest $request) {
    return parent::readFormValuesFromRequest($request) + array(
      self::KEY_REQUIRE_SECURE => $request->getBool(self::KEY_REQUIRE_SECURE),
    );
  }

  public function extendEditForm(
    AphrontRequest $request,
    AphrontFormView $form,
    array $values,
    array $issues) {

    parent::extendEditForm($request, $form, $values, $issues);

    $key_require = self::KEY_REQUIRE_SECURE;
    $v_require = idx($values, $key_require);

    $form
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            $key_require,
            $v_require,
            pht(
              "%s ".
              "Require users to enable 'secure browsing' on Facebook in order ".
              "to use Facebook to authenticate with Phabricator. This ".
              "improves security by preventing an attacker from capturing ".
              "an insecure Facebook session and escalating it into a ".
              "Phabricator session. Enabling it is recommended.",
              hsprintf(
                '<strong>%s</strong>',
                pht('Require Secure Browsing:')))));
  }

  public function renderConfigPropertyTransactionTitle(
    PhabricatorAuthProviderConfigTransaction $xaction) {

    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();
    $key = $xaction->getMetadataValue(
      PhabricatorAuthProviderConfigTransaction::PROPERTY_KEY);

    switch ($key) {
      case self::KEY_REQUIRE_SECURE:
        if ($new) {
          return pht(
            '%s turned "Require Secure Browsing" on.',
            $xaction->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s turned "Require Secure Browsing" off.',
            $xaction->renderHandleLink($author_phid));
        }
    }

    return parent::renderConfigPropertyTransactionTitle($xaction);
  }

  public static function getFacebookApplicationID() {
    $providers = PhabricatorAuthProvider::getAllProviders();
    $fb_provider = idx($providers, 'facebook:facebook.com');
    if (!$fb_provider) {
      return null;
    }

    return $fb_provider->getProperty(
      PhabricatorAuthProviderOAuth::PROPERTY_APP_ID);
  }

}
