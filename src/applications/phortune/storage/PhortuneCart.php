<?php

final class PhortuneCart extends PhortuneDAO
  implements PhabricatorPolicyInterface {

  const STATUS_BUILDING = 'cart:building';
  const STATUS_READY = 'cart:ready';
  const STATUS_PURCHASING = 'cart:purchasing';
  const STATUS_CHARGED = 'cart:charged';
  const STATUS_PURCHASED = 'cart:purchased';

  protected $accountPHID;
  protected $authorPHID;
  protected $cartClass;
  protected $status;
  protected $metadata = array();

  private $account = self::ATTACHABLE;
  private $purchases = self::ATTACHABLE;
  private $implementation = self::ATTACHABLE;

  public static function initializeNewCart(
    PhabricatorUser $actor,
    PhortuneAccount $account) {
    $cart = id(new PhortuneCart())
      ->setAuthorPHID($actor->getPHID())
      ->setStatus(self::STATUS_BUILDING)
      ->setAccountPHID($account->getPHID());

    $cart->account = $account;
    $cart->purchases = array();

    return $cart;
  }

  public function newPurchase(
    PhabricatorUser $actor,
    PhortuneProduct $product) {

    $purchase = PhortunePurchase::initializeNewPurchase($actor, $product)
      ->setAccountPHID($this->getAccount()->getPHID())
      ->setCartPHID($this->getPHID())
      ->save();

    $this->purchases[] = $purchase;

    return $purchase;
  }

  public function activateCart() {
    $this->setStatus(self::STATUS_READY)->save();
    return $this;
  }

  public function didApplyCharge(PhortuneCharge $charge) {
    if ($this->getStatus() !== self::STATUS_PURCHASING) {
      throw new Exception(
        pht(
          'Cart has wrong status ("%s") to call didApplyCharge(), expected '.
          '"%s".',
          $this->getStatus(),
          self::STATUS_PURCHASING));
    }

    $this->setStatus(self::STATUS_CHARGED)->save();

    foreach ($this->purchases as $purchase) {
      $purchase->getProduct()->didPurchaseProduct($purchase);
    }

    $this->setStatus(self::STATUS_PURCHASED)->save();

    return $this;
  }


  public function getDoneURI() {
    return $this->getImplementation()->getDoneURI($this);
  }

  public function getCancelURI() {
    return $this->getImplementation()->getCancelURI($this);
  }

  public function getDetailURI() {
    return '/phortune/cart/'.$this->getID().'/';
  }

  public function getCheckoutURI() {
    return '/phortune/cart/'.$this->getID().'/checkout/';
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'status' => 'text32',
        'cartClass' => 'text128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_account' => array(
          'columns' => array('accountPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_CART);
  }

  public function attachPurchases(array $purchases) {
    assert_instances_of($purchases, 'PhortunePurchase');
    $this->purchases = $purchases;
    return $this;
  }

  public function getPurchases() {
    return $this->assertAttached($this->purchases);
  }

  public function attachAccount(PhortuneAccount $account) {
    $this->account = $account;
    return $this;
  }

  public function getAccount() {
    return $this->assertAttached($this->account);
  }

  public function attachImplementation(
    PhortuneCartImplementation $implementation) {
    $this->implementation = $implementation;
    return $this;
  }

  public function getImplementation() {
    return $this->assertAttached($this->implementation);
  }

  public function getTotalPriceAsCurrency() {
    $prices = array();
    foreach ($this->getPurchases() as $purchase) {
      $prices[] = $purchase->getTotalPriceAsCurrency();
    }

    return PhortuneCurrency::newFromList($prices);
  }

  public function setMetadataValue($key, $value) {
    $this->metadata[$key] = $value;
    return $this;
  }

  public function getMetadataValue($key, $default = null) {
    return idx($this->metadata, $key, $default);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getAccount()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getAccount()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Carts inherit the policies of the associated account.');
  }

}
