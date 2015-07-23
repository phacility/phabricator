<?php

/**
 * A product is something users can purchase.
 */
final class PhortuneProduct extends PhortuneDAO
  implements PhabricatorPolicyInterface {

  protected $productClassKey;
  protected $productClass;
  protected $productRefKey;
  protected $productRef;
  protected $metadata = array();

  private $implementation = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'productClassKey' => 'bytes12',
        'productClass' => 'text128',
        'productRefKey' => 'bytes12',
        'productRef' => 'text128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_product' => array(
          'columns' => array('productClassKey', 'productRefKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhortuneProductPHIDType::TYPECONST);
  }

  public static function initializeNewProduct() {
    return id(new PhortuneProduct());
  }

  public function attachImplementation(PhortuneProductImplementation $impl) {
    $this->implementation = $impl;
  }

  public function getImplementation() {
    return $this->assertAttached($this->implementation);
  }

  public function save() {
    $this->productClassKey = PhabricatorHash::digestForIndex(
      $this->productClass);

    $this->productRefKey = PhabricatorHash::digestForIndex(
      $this->productRef);

    return parent::save();
  }

  public function getPriceAsCurrency() {
    return $this->getImplementation()->getPriceAsCurrency($this);
  }

  public function getProductName() {
    return $this->getImplementation()->getName($this);
  }

  public function getPurchaseName(PhortunePurchase $purchase) {
    return $this->getImplementation()->getPurchaseName($this, $purchase);
  }

  public function didPurchaseProduct(PhortunePurchase $purchase) {
    return $this->getImplementation()->didPurchaseProduct($this, $purchase);
  }

  public function didRefundProduct(
    PhortunePurchase $purchase,
    PhortuneCurrency $amount) {
    return $this->getImplementation()->didRefundProduct(
      $this,
      $purchase,
      $amount);
  }

  public function getPurchaseURI(PhortunePurchase $purchase) {
    return $this->getImplementation()->getPurchaseURI(
      $this,
      $purchase);
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
