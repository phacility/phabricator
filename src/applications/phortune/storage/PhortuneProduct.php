<?php

/**
 * A product is something users can purchase.
 */
final class PhortuneProduct extends PhortuneDAO
  implements PhabricatorPolicyInterface {

  protected $productName;
  protected $priceAsCurrency;
  protected $metadata;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_APPLICATION_SERIALIZERS => array(
        'priceAsCurrency' => new PhortuneCurrencySerializer(),
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'productName' => 'text255',
        'status' => 'text64',
        'priceAsCurrency' => 'text64',
        'billingIntervalInMonths' => 'uint32?',
        'trialPeriodInDays' => 'uint32?',
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_PDCT);
  }

  public static function initializeNewProduct() {
    return id(new PhortuneProduct())
      ->setPriceAsCurrency(PhortuneCurrency::newEmptyCurrency());
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

  public function describeAutomaticCapability($capability) {
    return null;
  }

}
