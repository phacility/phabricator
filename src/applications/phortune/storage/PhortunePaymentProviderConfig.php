<?php

final class PhortunePaymentProviderConfig extends PhortuneDAO
  implements PhabricatorPolicyInterface {

  protected $merchantPHID;
  protected $providerClassKey;
  protected $providerClass;
  protected $isEnabled;
  protected $metadata = array();

  private $merchant = self::ATTACHABLE;

  public static function initializeNewProvider(
    PhortuneMerchant $merchant) {
    return id(new PhortunePaymentProviderConfig())
      ->setMerchantPHID($merchant->getPHID())
      ->setIsEnabled(1);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'providerClassKey' => 'bytes12',
        'providerClass' => 'text128',
        'isEnabled' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_merchant' => array(
          'columns' => array('merchantPHID', 'providerClassKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function save() {
    $this->providerClassKey = PhabricatorHash::digestForIndex(
      $this->providerClass);

    return parent::save();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhortunePaymentProviderPHIDType::TYPECONST);
  }

  public function attachMerchant(PhortuneMerchant $merchant) {
    $this->merchant = $merchant;
    return $this;
  }

  public function getMerchant() {
    return $this->assertAttached($this->merchant);
  }

  public function getMetadataValue($key, $default = null) {
    return idx($this->metadata, $key, $default);
  }

  public function setMetadataValue($key, $value) {
    $this->metadata[$key] = $value;
    return $this;
  }

  public function buildProvider() {
    return newv($this->getProviderClass(), array())
      ->setProviderConfig($this);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getMerchant()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getMerchant()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Providers have the policies of their merchant.');
  }

}
