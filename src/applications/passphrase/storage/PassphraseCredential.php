<?php

final class PassphraseCredential extends PassphraseDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorFlaggableInterface,
    PhabricatorSubscribableInterface,
    PhabricatorDestructibleInterface,
    PhabricatorSpacesInterface,
    PhabricatorFulltextInterface,
    PhabricatorFerretInterface {

  protected $name;
  protected $credentialType;
  protected $providesType;
  protected $viewPolicy;
  protected $editPolicy;
  protected $description;
  protected $username;
  protected $secretID;
  protected $isDestroyed;
  protected $isLocked = 0;
  protected $allowConduit = 0;
  protected $authorPHID;
  protected $spacePHID;

  private $secret = self::ATTACHABLE;
  private $implementation = self::ATTACHABLE;

  public static function initializeNewCredential(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorPassphraseApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(PassphraseDefaultViewCapability::CAPABILITY);
    $edit_policy = $app->getPolicy(PassphraseDefaultEditCapability::CAPABILITY);

    return id(new PassphraseCredential())
      ->setName('')
      ->setUsername('')
      ->setDescription('')
      ->setIsDestroyed(0)
      ->setAuthorPHID($actor->getPHID())
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy)
      ->setSpacePHID($actor->getDefaultSpacePHID());
  }

  public function getMonogram() {
    return 'K'.$this->getID();
  }

  public function getURI() {
    return '/'.$this->getMonogram();
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'credentialType' => 'text64',
        'providesType' => 'text64',
        'description' => 'text',
        'username' => 'text255',
        'secretID' => 'id?',
        'isDestroyed' => 'bool',
        'isLocked' => 'bool',
        'allowConduit' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_secret' => array(
          'columns' => array('secretID'),
          'unique' => true,
        ),
        'key_type' => array(
          'columns' => array('credentialType'),
        ),
        'key_provides' => array(
          'columns' => array('providesType'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PassphraseCredentialPHIDType::TYPECONST);
  }

  public function attachSecret(PhutilOpaqueEnvelope $secret = null) {
    $this->secret = $secret;
    return $this;
  }

  public function getSecret() {
    return $this->assertAttached($this->secret);
  }

  public function getCredentialTypeImplementation() {
    $type = $this->getCredentialType();
    return PassphraseCredentialType::getTypeByConstant($type);
  }

  public function attachImplementation(PassphraseCredentialType $impl) {
    $this->implementation = $impl;
    return $this;
  }

  public function getImplementation() {
    return $this->assertAttached($this->implementation);
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PassphraseCredentialTransactionEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PassphraseCredentialTransaction();
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
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return false;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */

  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $secrets = id(new PassphraseSecret())->loadAllWhere(
        'id = %d',
        $this->getSecretID());
      foreach ($secrets as $secret) {
        $secret->delete();
      }
      $this->delete();
    $this->saveTransaction();
  }


/* -(  PhabricatorSpacesInterface  )----------------------------------------- */


  public function getSpacePHID() {
    return $this->spacePHID;
  }


/* -(  PhabricatorFulltextInterface  )--------------------------------------- */


  public function newFulltextEngine() {
    return new PassphraseCredentialFulltextEngine();
  }


/* -(  PhabricatorFerretInterface  )----------------------------------------- */


  public function newFerretEngine() {
    return new PassphraseCredentialFerretEngine();
  }


}
