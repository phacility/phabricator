<?php

final class PhabricatorAuthSSHKey
  extends PhabricatorAuthDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface,
    PhabricatorApplicationTransactionInterface {

  protected $objectPHID;
  protected $name;
  protected $keyType;
  protected $keyIndex;
  protected $keyBody;
  protected $keyComment = '';
  protected $isTrusted = 0;
  protected $isActive;

  private $object = self::ATTACHABLE;

  public static function initializeNewSSHKey(
    PhabricatorUser $viewer,
    PhabricatorSSHPublicKeyInterface $object) {

    // You must be able to edit an object to create a new key on it.
    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $object,
      PhabricatorPolicyCapability::CAN_EDIT);

    $object_phid = $object->getPHID();

    return id(new self())
      ->setIsActive(1)
      ->setObjectPHID($object_phid)
      ->attachObject($object);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'keyType' => 'text255',
        'keyIndex' => 'bytes12',
        'keyBody' => 'text',
        'keyComment' => 'text255',
        'isTrusted' => 'bool',
        'isActive' => 'bool?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID'),
        ),
        'key_active' => array(
          'columns' => array('isActive', 'objectPHID'),
        ),
        // NOTE: This unique key includes a nullable column, effectively
        // constraining uniqueness on active keys only.
        'key_activeunique' => array(
          'columns' => array('keyIndex', 'isActive'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function save() {
    $this->setKeyIndex($this->toPublicKey()->getHash());
    return parent::save();
  }

  public function toPublicKey() {
    return PhabricatorAuthSSHPublicKey::newFromStoredKey($this);
  }

  public function getEntireKey() {
    $parts = array(
      $this->getKeyType(),
      $this->getKeyBody(),
      $this->getKeyComment(),
    );
    return trim(implode(' ', $parts));
  }

  public function getObject() {
    return $this->assertAttached($this->object);
  }

  public function attachObject(PhabricatorSSHPublicKeyInterface $object) {
    $this->object = $object;
    return $this;
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorAuthSSHKeyPHIDType::TYPECONST);
  }

  public function getURI() {
    $id = $this->getID();
    return "/auth/sshkey/view/{$id}/";
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    if (!$this->getIsActive()) {
      if ($capability == PhabricatorPolicyCapability::CAN_EDIT) {
        return PhabricatorPolicies::POLICY_NOONE;
      }
    }

    return $this->getObject()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if (!$this->getIsActive()) {
      return false;
    }

    return $this->getObject()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    if (!$this->getIsACtive()) {
      return pht(
        'Revoked SSH keys can not be edited or reinstated.');
    }

    return pht(
      'SSH keys inherit the policies of the user or object they authenticate.');
  }

/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
    $this->delete();
    $this->saveTransaction();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorAuthSSHKeyEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorAuthSSHKeyTransaction();
  }

}
