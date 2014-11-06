<?php

final class PhabricatorUserSSHKey
  extends PhabricatorUserDAO
  implements PhabricatorPolicyInterface {

  protected $userPHID;
  protected $name;
  protected $keyType;
  protected $keyBody;
  protected $keyHash;
  protected $keyComment;

  private $object = self::ATTACHABLE;

  public function getObjectPHID() {
    return $this->getUserPHID();
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'keyHash' => 'bytes32',
        'keyComment' => 'text255?',

        // T6203/NULLABILITY
        // These seem like they should not be nullable.
        'name' => 'text255?',
        'keyType' => 'text255?',
        'keyBody' => 'text?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'userPHID' => array(
          'columns' => array('userPHID'),
        ),
        'keyHash' => array(
          'columns' => array('keyHash'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
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

  public function attachObject($object) {
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
