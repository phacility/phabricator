<?php

final class PhabricatorAuthProviderConfig
  extends PhabricatorAuthDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  protected $providerClass;
  protected $providerType;
  protected $providerDomain;

  protected $isEnabled;
  protected $shouldAllowLogin         = 0;
  protected $shouldAllowRegistration  = 0;
  protected $shouldAllowLink          = 0;
  protected $shouldAllowUnlink        = 0;
  protected $shouldTrustEmails        = 0;
  protected $shouldAutoLogin          = 0;

  protected $properties = array();

  private $provider;

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorAuthAuthProviderPHIDType::TYPECONST);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'isEnabled' => 'bool',
        'providerClass' => 'text128',
        'providerType' => 'text32',
        'providerDomain' => 'text128',
        'shouldAllowLogin' => 'bool',
        'shouldAllowRegistration' => 'bool',
        'shouldAllowLink' => 'bool',
        'shouldAllowUnlink' => 'bool',
        'shouldTrustEmails' => 'bool',
        'shouldAutoLogin' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_provider' => array(
          'columns' => array('providerType', 'providerDomain'),
          'unique' => true,
        ),
        'key_class' => array(
          'columns' => array('providerClass'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getProvider() {
    if (!$this->provider) {
      $base = PhabricatorAuthProvider::getAllBaseProviders();
      $found = null;
      foreach ($base as $provider) {
        if (get_class($provider) == $this->providerClass) {
          $found = $provider;
          break;
        }
      }
      if ($found) {
        $this->provider = id(clone $found)->attachProviderConfig($this);
      }
    }
    return $this->provider;
  }

  public function getURI() {
    return '/auth/config/view/'.$this->getID().'/';
  }

  public function getObjectName() {
    return pht('Auth Provider %d', $this->getID());
  }

  public function getDisplayName() {
    return $this->getProvider()->getProviderName();
  }

  public function getSortVector() {
    return id(new PhutilSortVector())
      ->addString($this->getDisplayName());
  }

  public function newIconView() {
    return $this->getProvider()->newIconView();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorAuthProviderConfigEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorAuthProviderConfigTransaction();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::POLICY_USER;
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_ADMIN;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $viewer = $engine->getViewer();
    $config_phid = $this->getPHID();

    $accounts = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->withProviderConfigPHIDs(array($config_phid))
      ->newIterator();
    foreach ($accounts as $account) {
      $engine->destroyObject($account);
    }

    $identifiers = id(new PhabricatorExternalAccountIdentifierQuery())
      ->setViewer($viewer)
      ->withProviderConfigPHIDs(array($config_phid))
      ->newIterator();
    foreach ($identifiers as $identifier) {
      $engine->destroyObject($identifier);
    }

    $this->delete();
  }

}
