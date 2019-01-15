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
