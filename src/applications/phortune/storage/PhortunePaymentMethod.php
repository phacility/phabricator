<?php

/**
 * A payment method is a credit card; it is associated with an account and
 * charges can be made against it.
 */
final class PhortunePaymentMethod extends PhortuneDAO
  implements PhabricatorPolicyInterface {

  const STATUS_ACTIVE     = 'payment:active';
  const STATUS_FAILED     = 'payment:failed';
  const STATUS_REMOVED    = 'payment:removed';

  protected $name = '';
  protected $status;
  protected $accountPHID;
  protected $authorPHID;
  protected $expires;
  protected $metadata = array();
  protected $brand;
  protected $lastFourDigits;
  protected $providerType;
  protected $providerDomain;

  private $account;

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
      PhabricatorPHIDConstants::PHID_TYPE_PAYM);
  }

  public function attachAccount(PhortuneAccount $account) {
    $this->account = $account;
    return $this;
  }

  public function getAccount() {
    if (!$this->account) {
      throw new Exception("Call attachAccount() before getAccount()!");
    }
    return $this->account;
  }

  public function getDescription() {
    return '...';
  }

  public function getMetadataValue($key, $default = null) {
    return idx($this->getMetadata(), $key, $default);
  }

  public function setMetadataValue($key, $value) {
    $this->metadata[$key] = $value;
    return $this;
  }

  public function buildPaymentProvider() {
    $providers = PhortunePaymentProvider::getAllProviders();

    $accept = array();
    foreach ($providers as $provider) {
      if ($provider->canHandlePaymentMethod($this)) {
        $accept[] = $provider;
      }
    }

    if (!$accept) {
      throw new PhortuneNoPaymentProviderException($this);
    }

    if (count($accept) > 1) {
      throw new PhortuneMultiplePaymentProvidersException($this, $accept);
    }

    return head($accept);
  }

  public function setExpires($year, $month) {
    $this->expires = $year.'-'.$month;
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
    return $this->getAccount()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getAccount()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

}
