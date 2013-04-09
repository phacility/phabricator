<?php

/**
 * A charge is a charge (or credit) against an account and represents an actual
 * transfer of funds. Each charge is normally associated with a product, but a
 * product may have multiple charges. For example, a subscription may have
 * monthly charges, or a product may have a failed charge followed by a
 * successful charge.
 */
final class PhortuneCharge extends PhortuneDAO {

  const STATUS_PENDING    = 'charge:pending';
  const STATUS_AUTHORIZED = 'charge:authorized';
  const STATUS_CHARGED    = 'charge:charged';
  const STATUS_FAILED     = 'charge:failed';

  protected $accountPHID;
  protected $purchasePHID;
  protected $paymentMethodPHID;
  protected $amountInCents;
  protected $status;
  protected $metadata;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_CHRG);
  }

}
