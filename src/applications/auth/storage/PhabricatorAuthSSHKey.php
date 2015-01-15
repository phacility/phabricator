<?php

final class PhabricatorAuthSSHKey
  extends PhabricatorAuthDAO
  implements PhabricatorPolicyInterface {

  protected $objectPHID;
  protected $name;
  protected $keyType;
  protected $keyIndex;
  protected $keyBody;
  protected $keyComment = '';
  protected $isTrusted = 0;

  private $object = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'keyType' => 'text255',
        'keyIndex' => 'bytes12',
        'keyBody' => 'text',
        'keyComment' => 'text255',
        'isTrusted' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID'),
        ),
        'key_unique' => array(
          'columns' => array('keyIndex'),
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




/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getObject()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getObject()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'SSH keys inherit the policies of the user or object they authenticate.');
  }

}
