<?php

class Stripe_Refund extends Stripe_ApiResource
{
  /**
   * @return string The API URL for this Stripe refund.
   */
  public function instanceUrl()
  {
    $id = $this['id'];
    $charge = $this['charge'];
    if (!$id) {
      throw new Stripe_InvalidRequestError(
          "Could not determine which URL to request: " .
          "class instance has invalid ID: $id",
          null
      );
    }
    $id = Stripe_ApiRequestor::utf8($id);
    $charge = Stripe_ApiRequestor::utf8($charge);

    $base = self::classUrl('Stripe_Charge');
    $chargeExtn = urlencode($charge);
    $extn = urlencode($id);
    return "$base/$chargeExtn/refunds/$extn";
  }

  /**
   * @return Stripe_Refund The saved refund.
   */
  public function save()
  {
    $class = get_class();
    return self::_scopedSave($class);
  }
}
