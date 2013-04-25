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
    );
  }

}
