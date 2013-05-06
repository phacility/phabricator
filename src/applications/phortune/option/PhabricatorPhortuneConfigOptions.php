<?php

final class PhabricatorPhortuneConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Phortune");
  }

  public function getDescription() {
    return pht("Configure payments and billing.");
  }

  public function getOptions() {
    return array(
      $this->newOption('phortune.stripe.publishable-key', 'string', null)
        ->setLocked(true)
        ->setDescription(pht('Stripe publishable key.')),
      $this->newOption('phortune.stripe.secret-key', 'string', null)
        ->setHidden(true)
        ->setDescription(pht('Stripe secret key.')),
      $this->newOption('phortune.balanced.marketplace-uri', 'string', null)
        ->setLocked(true)
        ->setDescription(pht('Balanced Marketplace URI.')),
      $this->newOption('phortune.balanced.secret-key', 'string', null)
        ->setHidden(true)
        ->setDescription(pht('Balanced secret key.')),
      $this->newOption('phortune.test.enabled', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Enable Test Provider'),
            pht('Disable Test Provider'),
          ))
        ->setSummary(pht('Enable test payment provider.'))
        ->setDescription(
          pht(
            "Enable the test payment provider.\n\n".
            "NOTE: Enabling this provider gives all users infinite free ".
            "money! You should enable it **ONLY** for testing and ".
            "development."))
        ->setLocked(true),
      $this->newOption('phortune.paypal.api-username', 'string', null)
        ->setLocked(true)
        ->setDescription(
          pht('PayPal API username.')),
      $this->newOption('phortune.paypal.api-password', 'string', null)
        ->setHidden(true)
        ->setDescription(
          pht('PayPal API password.')),
      $this->newOption('phortune.paypal.api-signature', 'string', null)
        ->setHidden(true)
        ->setDescription(
          pht('PayPal API signature.')),
    );
  }

}
