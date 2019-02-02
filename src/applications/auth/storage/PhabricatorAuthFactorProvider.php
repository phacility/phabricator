<?php

final class PhabricatorAuthFactorProvider
  extends PhabricatorAuthDAO
  implements
     PhabricatorApplicationTransactionInterface,
     PhabricatorPolicyInterface,
     PhabricatorExtendedPolicyInterface,
     PhabricatorEditEngineMFAInterface {

  protected $providerFactorKey;
  protected $name;
  protected $status;
  protected $properties = array();

  private $factor = self::ATTACHABLE;

  public static function initializeNewProvider(PhabricatorAuthFactor $factor) {
    return id(new self())
      ->setProviderFactorKey($factor->getFactorKey())
      ->attachFactor($factor)
      ->setStatus(PhabricatorAuthFactorProviderStatus::STATUS_ACTIVE);
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

  public function getEnrollMessage() {
    return $this->getAuthFactorProviderProperty('enroll-message');
  }

  public function setEnrollMessage($message) {
    return $this->setAuthFactorProviderProperty('enroll-message', $message);
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

  public function newIconView() {
    return $this->getFactor()->newIconView();
  }

  public function getDisplayDescription() {
    return $this->getFactor()->getFactorDescription();
  }

  public function processAddFactorForm(
    AphrontFormView $form,
    AphrontRequest $request,
    PhabricatorUser $user) {

    $factor = $this->getFactor();

    $config = $factor->processAddFactorForm($this, $form, $request, $user);
    if ($config) {
      $config->setFactorProviderPHID($this->getPHID());
    }

    return $config;
  }

  public function newSortVector() {
    $factor = $this->getFactor();

    return id(new PhutilSortVector())
      ->addInt($factor->getFactorOrder())
      ->addInt($this->getID());
  }

  public function getEnrollDescription(PhabricatorUser $user) {
    return $this->getFactor()->getEnrollDescription($this, $user);
  }

  public function getEnrollButtonText(PhabricatorUser $user) {
    return $this->getFactor()->getEnrollButtonText($this, $user);
  }

  public function newStatus() {
    $status_key = $this->getStatus();
    return PhabricatorAuthFactorProviderStatus::newForStatus($status_key);
  }

  public function canCreateNewConfiguration(PhabricatorUser $user) {
    return $this->getFactor()->canCreateNewConfiguration($this, $user);
  }

  public function getConfigurationCreateDescription(PhabricatorUser $user) {
    return $this->getFactor()->getConfigurationCreateDescription($this, $user);
  }

  public function getConfigurationListDetails(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer) {
    return $this->getFactor()->getConfigurationListDetails(
      $config,
      $this,
      $viewer);
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


/* -(  PhabricatorEditEngineMFAInterface  )---------------------------------- */


  public function newEditEngineMFAEngine() {
    return new PhabricatorAuthFactorProviderMFAEngine();
  }

}
