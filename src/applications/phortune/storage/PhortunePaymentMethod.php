<?php

/**
 * A payment method is a credit card; it is associated with an account and
 * charges can be made against it.
 */
final class PhortunePaymentMethod extends PhortuneDAO
  implements PhabricatorPolicyInterface {

  const STATUS_ACTIVE     = 'payment:active';
  const STATUS_DISABLED   = 'payment:disabled';

  protected $name = '';
  protected $status;
  protected $accountPHID;
  protected $authorPHID;
  protected $merchantPHID;
  protected $providerPHID;
  protected $expires;
  protected $metadata = array();
  protected $brand;
  protected $lastFourDigits;

  private $account = self::ATTACHABLE;
  private $merchant = self::ATTACHABLE;
  private $providerConfig = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'status' => 'text64',
        'brand' => 'text64',
        'expires' => 'text16',
        'lastFourDigits' => 'text16',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_account' => array(
          'columns' => array('accountPHID', 'status'),
        ),
        'key_merchant' => array(
          'columns' => array('merchantPHID', 'accountPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhortunePaymentMethodPHIDType::TYPECONST);
  }

  public function attachAccount(PhortuneAccount $account) {
    $this->account = $account;
    return $this;
  }

  public function getAccount() {
    return $this->assertAttached($this->account);
  }

  public function attachMerchant(PhortuneMerchant $merchant) {
    $this->merchant = $merchant;
    return $this;
  }

  public function getMerchant() {
    return $this->assertAttached($this->merchant);
  }

  public function attachProviderConfig(PhortunePaymentProviderConfig $config) {
    $this->providerConfig = $config;
    return $this;
  }

  public function getProviderConfig() {
    return $this->assertAttached($this->providerConfig);
  }

  public function getDescription() {
    $provider = $this->buildPaymentProvider();
    return $provider->getPaymentMethodProviderDescription();
  }

  public function getMetadataValue($key, $default = null) {
    return idx($this->getMetadata(), $key, $default);
  }

  public function setMetadataValue($key, $value) {
    $this->metadata[$key] = $value;
    return $this;
  }

  public function buildPaymentProvider() {
    return $this->getProviderConfig()->buildProvider();
  }

  public function getDisplayName() {
    if (strlen($this->name)) {
      return $this->name;
    }

    $provider = $this->buildPaymentProvider();
    return $provider->getDefaultPaymentMethodDisplayName($this);
  }

  public function getFullDisplayName() {
    return pht('%s (%s)', $this->getDisplayName(), $this->getSummary());
  }

  public function getSummary() {
    return pht('%s %s', $this->getBrand(), $this->getLastFourDigits());
  }

  public function setExpires($year, $month) {
    $this->expires = $year.'-'.$month;
    return $this;
  }

  public function getDisplayExpires() {
    list($year, $month) = explode('-', $this->getExpires());
    $month = sprintf('%02d', $month);
    $year = substr($year, -2);
    return $month.'/'.$year;
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
    return $this->getAccount()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'Members of an account can always view and edit its payment methods.');
  }

}
