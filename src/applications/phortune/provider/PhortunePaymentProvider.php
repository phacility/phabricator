<?php

abstract class PhortunePaymentProvider {

  /**
   * Determine of a provider can handle a payment method.
   *
   * @return bool True if this provider can apply charges to the payment
   *              method.
   */
  abstract public function canHandlePaymentMethod(
    PhortunePaymentMethod $method);

  abstract protected function executeCharge(
    PhortunePaymentMethod $payment_method,
    PhortuneCharge $charge);

}
