<?php

/**
 * A charge is a charge (or credit) against an account and represents an actual
 * transfer of funds. Each charge is normally associated with a cart, but a
 * cart may have multiple charges. For example, a product may have a failed
 * charge followed by a successful charge.
 */
final class PhortuneCharge extends PhortuneDAO
  implements PhabricatorPolicyInterface {

  const STATUS_CHARGING   = 'charge:charging';
  const STATUS_CHARGED    = 'charge:charged';
  const STATUS_FAILED     = 'charge:failed';

  protected $accountPHID;
  protected $authorPHID;
  protected $cartPHID;
  protected $providerPHID;
  protected $merchantPHID;
  protected $paymentMethodPHID;
  protected $amountAsCurrency;
  protected $status;
  protected $metadata = array();

  private $account = self::ATTACHABLE;
  private $cart = self::ATTACHABLE;

  public static function initializeNewCharge() {
    return id(new PhortuneCharge())
      ->setStatus(self::STATUS_CHARGING);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_APPLICATION_SERIALIZERS => array(
        'amountAsCurrency' => new PhortuneCurrencySerializer(),
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'paymentProviderKey' => 'text128',
        'paymentMethodPHID' => 'phid?',
        'amountAsCurrency' => 'text64',
        'status' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_cart' => array(
          'columns' => array('cartPHID'),
        ),
        'key_account' => array(
          'columns' => array('accountPHID'),
        ),
        'key_merchant' => array(
          'columns' => array('merchantPHID'),
        ),
        'key_provider' => array(
          'columns' => array('providerPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function getStatusNameMap() {
    return array(
      self::STATUS_CHARGING => pht('Charging'),
      self::STATUS_CHARGED => pht('Charged'),
      self::STATUS_FAILED => pht('Failed'),
    );
  }

  public static function getNameForStatus($status) {
    return idx(self::getStatusNameMap(), $status, pht('Unknown'));
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhortuneChargePHIDType::TYPECONST);
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
