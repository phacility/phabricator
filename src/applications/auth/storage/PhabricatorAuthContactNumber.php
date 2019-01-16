<?php


final class PhabricatorAuthContactNumber
  extends PhabricatorAuthDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  protected $objectPHID;
  protected $contactNumber;
  protected $uniqueKey;
  protected $status;
  protected $properties = array();

  const STATUS_ACTIVE = 'active';
  const STATUS_DISABLED = 'disabled';

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'contactNumber' => 'text255',
        'status' => 'text32',
        'uniqueKey' => 'bytes12?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID'),
        ),
        'key_unique' => array(
          'columns' => array('uniqueKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function initializeNewContactNumber($object) {
    return id(new self())
      ->setStatus(self::STATUS_ACTIVE)
      ->setObjectPHID($object->getPHID());
  }

  public function getPHIDType() {
    return PhabricatorAuthContactNumberPHIDType::TYPECONST;
  }

  public function getURI() {
    return urisprintf('/auth/contact/%s/', $this->getID());
  }

  public function getObjectName() {
    return pht('Contact Number %d', $this->getID());
  }

  public function getDisplayName() {
    return $this->getContactNumber();
  }

  public function isDisabled() {
    return ($this->getStatus() === self::STATUS_DISABLED);
  }

  public function newIconView() {
    if ($this->isDisabled()) {
      return id(new PHUIIconView())
        ->setIcon('fa-ban', 'grey')
        ->setTooltip(pht('Disabled'));
    }

    return id(new PHUIIconView())
      ->setIcon('fa-mobile', 'green')
      ->setTooltip(pht('Active Phone Number'));
  }

  public function newUniqueKey() {
    $parts = array(
      // This is future-proofing for a world where we have multiple types
      // of contact numbers, so we might be able to avoid re-hashing
      // everything.
      'phone',
      $this->getContactNumber(),
    );

    $parts = implode("\0", $parts);

    return PhabricatorHash::digestForIndex($parts);
  }

  public function save() {
    $this->uniqueKey = $this->newUniqueKey();
    return parent::save();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getObjectPHID();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $this->delete();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorAuthContactNumberEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorAuthContactNumberTransaction();
  }


}
