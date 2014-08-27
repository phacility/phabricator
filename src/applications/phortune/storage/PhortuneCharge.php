<?php

/**
 * A charge is a charge (or credit) against an account and represents an actual
 * transfer of funds. Each charge is normally associated with a cart, but a
 * cart may have multiple charges. For example, a product may have a failed
 * charge followed by a successful charge.
 */
final class PhortuneCharge extends PhortuneDAO
  implements PhabricatorPolicyInterface {

  const STATUS_PENDING    = 'charge:pending';
  const STATUS_AUTHORIZED = 'charge:authorized';
  const STATUS_CHARGING   = 'charge:charging';
  const STATUS_CHARGED    = 'charge:charged';
  const STATUS_FAILED     = 'charge:failed';

  protected $accountPHID;
  protected $authorPHID;
  protected $cartPHID;
  protected $paymentProviderKey;
  protected $paymentMethodPHID;
  protected $amountInCents;
  protected $status;
  protected $metadata = array();

  private $account = self::ATTACHABLE;
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
      PhabricatorPHIDConstants::PHID_TYPE_CHRG);
  }

  protected function didReadData() {
    // The payment processing code is strict about types.
    $this->amountInCents = (int)$this->amountInCents;
  }

  public function getMetadataValue($key, $default = null) {
    return idx($this->metadata, $key, $default);
  }

  public function setMetadataValue($key, $value) {
    $this->metadata[$key] = $value;
    return $this;
  }

  public function getAccount() {
    return $this->assertAttached($this->account);
  }

  public function attachAccount(PhortuneAccount $account) {
    $this->account = $account;
    return $this;
  }

  public function getCart() {
    return $this->assertAttached($this->cart);
  }

  public function attachCart(PhortuneCart $cart = null) {
    $this->cart = $cart;
    return $this;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getAccount()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getAccount()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Charges inherit the policies of the associated account.');
  }

}
