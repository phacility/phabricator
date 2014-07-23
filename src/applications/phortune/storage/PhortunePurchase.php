<?php

/**
 * A purchase represents a user buying something or a subscription to a plan.
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
  protected $basePriceInCents;
  protected $quantity;
  protected $totalPriceInCents;
  protected $status;
  protected $metadata;

  private $cart = self::ATTACHABLE;

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
      PhabricatorPHIDConstants::PHID_TYPE_PRCH);
  }

  public function attachCart(PhortuneCart $cart) {
    $this->cart = $cart;
    return $this;
  }

  public function getCart() {
    return $this->assertAttached($this->cart);
  }

  protected function didReadData() {
    // The payment processing code is strict about types.
    $this->basePriceInCents = (int)$this->basePriceInCents;
    $this->totalPriceInCents = (int)$this->totalPriceInCents;
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
