<?php

/**
 * A product is something users can purchase. It may be a one-time purchase,
 * or a plan which is billed monthly.
 */
final class PhortuneProduct extends PhortuneDAO {

  const TYPE_BILL_ONCE  = 'phortune:thing';
  const TYPE_BILL_PLAN  = 'phortune:plan';

  protected $productName;
  protected $priceInCents;
  protected $billingIntervalInMonths;
  protected $trialPeriodInDays;
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
      PhabricatorPHIDConstants::PHID_TYPE_PDCT);
  }



}
