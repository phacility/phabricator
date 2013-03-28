<?php

/**
 * A product is something users can purchase. It may be a one-time purchase,
 * or a plan which is billed monthly.
 */
final class PhortuneProduct extends PhortuneDAO
  implements PhabricatorPolicyInterface {

  const TYPE_BILL_ONCE      = 'phortune:thing';
  const TYPE_BILL_PLAN      = 'phortune:plan';

  const STATUS_ACTIVE       = 'product:active';
  const STATUS_DISABLED     = 'product:disabled';

  protected $productName;
  protected $productType;
  protected $status = self::STATUS_ACTIVE;
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

  public static function getTypeMap() {
    return array(
      self::TYPE_BILL_ONCE => pht('Product (Charged Once)'),
      self::TYPE_BILL_PLAN => pht('Flat Rate Plan (Charged Monthly)'),
    );
  }

  public function getTypeName() {
    return idx(self::getTypeMap(), $this->getProductType());
  }

  public function getPriceInCents() {
    $price = parent::getPriceInCents();
    if ($price === null) {
      return $price;
    } else {
      return (int)parent::getPriceInCents();
    }
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_USER;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
