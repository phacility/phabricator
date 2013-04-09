<?php

final class PhabricatorStripeConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Integration with Stripe");
  }

  public function getDescription() {
    return pht("Configure Stripe payments.");
  }

  public function getOptions() {
    return array(
      $this->newOption('stripe.publishable-key', 'string', null)
        ->setDescription(
          pht('Stripe publishable key.')),
      $this->newOption('stripe.secret-key', 'string', null)
        ->setDescription(
          pht('Stripe secret key.')),
    );
  }

}
