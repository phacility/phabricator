<?php


final class PhabricatorAuthFactorConfig
  extends PhabricatorAuthDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  protected $userPHID;
  protected $factorProviderPHID;
  protected $factorName;
  protected $factorSecret;
  protected $properties = array();

  private $sessionEngine;
  private $factorProvider = self::ATTACHABLE;
  private $mfaSyncToken;

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'factorName' => 'text',
        'factorSecret' => 'text',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_user' => array(
          'columns' => array('userPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorAuthAuthFactorPHIDType::TYPECONST;
  }

  public function attachFactorProvider(
    PhabricatorAuthFactorProvider $provider) {
    $this->factorProvider = $provider;
    return $this;
  }

  public function getFactorProvider() {
    return $this->assertAttached($this->factorProvider);
  }

  public function setSessionEngine(PhabricatorAuthSessionEngine $engine) {
    $this->sessionEngine = $engine;
    return $this;
  }

  public function getSessionEngine() {
    if (!$this->sessionEngine) {
      throw new PhutilInvalidStateException('setSessionEngine');
    }

    return $this->sessionEngine;
  }

  public function setMFASyncToken(PhabricatorAuthTemporaryToken $token) {
    $this->mfaSyncToken = $token;
    return $this;
  }

  public function getMFASyncToken() {
    return $this->mfaSyncToken;
  }

  public function getAuthFactorConfigProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setAuthFactorConfigProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function newSortVector() {
    return id(new PhutilSortVector())
      ->addInt($this->getFactorProvider()->newStatus()->getOrder())
      ->addInt($this->getID());
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getUserPHID();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($engine->getViewer())
      ->withPHIDs(array($this->getUserPHID()))
      ->executeOne();

    $this->delete();

    if ($user) {
      $user->updateMultiFactorEnrollment();
    }
  }

}
