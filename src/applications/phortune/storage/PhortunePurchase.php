<?php

/**
 * A purchase represents a user buying something.
 */
final class PhortunePurchase extends PhortuneDAO
  implements PhabricatorPolicyInterface {

  const STATUS_PENDING      = 'purchase:pending';
  const STATUS_PROCESSING   = 'purchase:processing';
  const STATUS_ACTIVE       = 'purchase:active';
  const STATUS_CANCELED     = 'purchase:canceled';
  const STATUS_DELIVERED    = 'purchase:delivered';
  const STATUS_FAILED       = 'purchase:failed';

  protected $productPHID;
  protected $accountPHID;
  protected $authorPHID;
  protected $cartPHID;
  protected $basePriceAsCurrency;
  protected $quantity;
  protected $status;
  protected $metadata = array();

  private $cart = self::ATTACHABLE;
  private $product = self::ATTACHABLE;

  public static function initializeNewPurchase(
    PhabricatorUser $actor,
    PhortuneProduct $product) {
    return id(new PhortunePurchase())
      ->setAuthorPHID($actor->getPHID())
      ->setProductPHID($product->getPHID())
      ->attachProduct($product)
      ->setQuantity(1)
      ->setStatus(self::STATUS_PENDING)
      ->setBasePriceAsCurrency($product->getPriceAsCurrency());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_APPLICATION_SERIALIZERS => array(
        'basePriceAsCurrency' => new PhortuneCurrencySerializer(),
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'cartPHID' => 'phid?',
        'basePriceAsCurrency' => 'text64',
        'quantity' => 'uint32',
        'status' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_cart' => array(
          'columns' => array('cartPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhortunePurchasePHIDType::TYPECONST);
  }

  public function attachCart(PhortuneCart $cart) {
    $this->cart = $cart;
    return $this;
  }

  public function getCart() {
    return $this->assertAttached($this->cart);
  }

  public function attachProduct(PhortuneProduct $product) {
    $this->product = $product;
    return $this;
  }

  public function getProduct() {
    return $this->assertAttached($this->product);
  }

  public function getFullDisplayName() {
    return $this->getProduct()->getPurchaseName($this);
  }

  public function getURI() {
    return $this->getProduct()->getPurchaseURI($this);
  }

  public function getTotalPriceAsCurrency() {
    $base = $this->getBasePriceAsCurrency();

    $price = PhortuneCurrency::newEmptyCurrency();
    for ($ii = 0; $ii < $this->getQuantity(); $ii++) {
      $price = $price->add($base);
    }

    return $price;
  }

  public function getMetadataValue($key, $default = null) {
    return idx($this->metadata, $key, $default);
  }

  public function setMetadataValue($key, $value) {
    $this->metadata[$key] = $value;
    return $this;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getCart()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getCart()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Purchases have the policies of their cart.');
  }

}
