<?php

final class PhabricatorAuthFactorProvider
  extends PhabricatorAuthDAO
  implements
     PhabricatorApplicationTransactionInterface,
     PhabricatorPolicyInterface,
     PhabricatorExtendedPolicyInterface {

  protected $providerFactorKey;
  protected $name;
  protected $status;
  protected $properties = array();

  private $factor = self::ATTACHABLE;

  const STATUS_ACTIVE = 'active';
  const STATUS_DEPRECATED = 'deprecated';
  const STATUS_DISABLED = 'disabled';

  public static function initializeNewProvider(PhabricatorAuthFactor $factor) {
    return id(new self())
      ->setProviderFactorKey($factor->getFactorKey())
      ->attachFactor($factor)
      ->setStatus(self::STATUS_ACTIVE);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'providerFactorKey' => 'text64',
        'name' => 'text255',
        'status' => 'text32',
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorAuthAuthFactorProviderPHIDType::TYPECONST;
  }

  public function getURI() {
    return '/auth/mfa/'.$this->getID().'/';
  }

  public function getObjectName() {
    return pht('MFA Provider %d', $this->getID());
  }

  public function getAuthFactorProviderProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setAuthFactorProviderProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function attachFactor(PhabricatorAuthFactor $factor) {
    $this->factor = $factor;
    return $this;
  }

  public function getFactor() {
    return $this->assertAttached($this->factor);
  }

  public function getDisplayName() {
    $name = $this->getName();
    if (strlen($name)) {
      return $name;
    }

    return $this->getFactor()->getFactorName();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorAuthFactorProviderEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorAuthFactorProviderTransaction();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    $extended = array();

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        break;
      case PhabricatorPolicyCapability::CAN_EDIT:
        $extended[] = array(
          new PhabricatorAuthApplication(),
          AuthManageProvidersCapability::CAPABILITY,
        );
        break;
    }

    return $extended;
  }


}
