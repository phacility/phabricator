<?php

final class PhortuneStripePaymentProvider extends PhortunePaymentProvider {

  public function canHandlePaymentMethod(PhortunePaymentMethod $method) {
    $type = $method->getMetadataValue('type');
    return ($type === 'stripe.customer');
  }

  /**
   * @phutil-external-symbol class Stripe_Charge
   */
  protected function executeCharge(
    PhortunePaymentMethod $method,
    PhortuneCharge $charge) {

    $secret_key = $this->getSecretKey();
    $params = array(
      'amount'      => $charge->getAmountInCents(),
      'currency'    => 'usd',
      'customer'    => $method->getMetadataValue('stripe.customerID'),
      'description' => $charge->getPHID(),
      'capture'     => true,
    );

    $stripe_charge = Stripe_Charge::create($params, $secret_key);
    $id = $stripe_charge->id;
    if (!$id) {
      throw new Exception("Stripe charge call did not return an ID!");
    }

    $charge->setMetadataValue('stripe.chargeID', $id);
  }

  private function getSecretKey() {
    return PhabricatorEnv::getEnvConfig('stripe.secret-key');
  }

}
