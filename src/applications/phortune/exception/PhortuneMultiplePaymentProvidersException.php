<?php

final class PhortuneMultiplePaymentProvidersException extends Exception {

  public function __construct(PhortunePaymentMethod $method, array $providers) {
    assert_instances_of($providers, 'PhortunePaymentProvider');
    $type = $method->getMetadataValue('type');

    $provider_names = array();
    foreach ($providers as $provider) {
      $provider_names[] = get_class($provider);
    }

    return parent::__construct(
      "More than one payment provider can handle charging payments for this ".
      "payment method. This is ambiguous and likely indicates that a payment ".
      "provider is not properly implemented. You may be able to use a ".
      "different payment method to complete this transaction. The payment ".
      "method type is '{$type}'. The providers claiming to handle it are: ".
      implode(', ', $provider_names).'.');
  }

}
