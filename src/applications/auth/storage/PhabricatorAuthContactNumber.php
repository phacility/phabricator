<?php


final class PhabricatorAuthContactNumber
  extends PhabricatorAuthDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface,
    PhabricatorEditEngineMFAInterface {

  protected $objectPHID;
  protected $contactNumber;
  protected $uniqueKey;
  protected $status;
  protected $isPrimary;
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
        'isPrimary' => 'bool',
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
      ->setObjectPHID($object->getPHID())
      ->setIsPrimary(0);
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

    if ($this->getIsPrimary()) {
      return id(new PHUIIconView())
        ->setIcon('fa-certificate', 'blue')
        ->setTooltip(pht('Primary Number'));
    }

    return id(new PHUIIconView())
      ->setIcon('fa-hashtag', 'bluegrey')
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
    // We require that active contact numbers be unique, but it's okay to
    // disable a number and then reuse it somewhere else.
    if ($this->isDisabled()) {
      $this->uniqueKey = null;
    } else {
      $this->uniqueKey = $this->newUniqueKey();
    }

    parent::save();

    return $this->updatePrimaryContactNumber();
  }

  private function updatePrimaryContactNumber() {
    // Update the "isPrimary" column so that at most one number is primary for
    // each user, and no disabled number is primary.

    $conn = $this->establishConnection('w');
    $this_id = (int)$this->getID();

    if ($this->getIsPrimary() && !$this->isDisabled()) {
      // If we're trying to make this number primary and it's active, great:
      // make this number the primary number.
      $primary_id = $this_id;
    } else {
      // If we aren't trying to make this number primary or it is disabled,
      // pick another number to make primary if we can. A number must be active
      // to become primary.

      // If there are multiple active numbers, pick the oldest one currently
      // marked primary (usually, this should mean that we just keep the
      // current primary number as primary).

      // If none are marked primary, just pick the oldest one.
      $primary_row = queryfx_one(
        $conn,
        'SELECT id FROM %R
          WHERE objectPHID = %s AND status = %s
          ORDER BY isPrimary DESC, id ASC
          LIMIT 1',
        $this,
        $this->getObjectPHID(),
        self::STATUS_ACTIVE);
      if ($primary_row) {
        $primary_id = (int)$primary_row['id'];
      } else {
        $primary_id = -1;
      }
    }

    // Set the chosen number to primary, and all other numbers to nonprimary.

    queryfx(
      $conn,
      'UPDATE %R SET isPrimary = IF(id = %d, 1, 0)
        WHERE objectPHID = %s',
      $this,
      $primary_id,
      $this->getObjectPHID());

    $this->setIsPrimary((int)($primary_id === $this_id));

    return $this;
  }

  public static function getStatusNameMap() {
    return ipull(self::getStatusPropertyMap(), 'name');
  }

  private static function getStatusPropertyMap() {
    return array(
      self::STATUS_ACTIVE => array(
        'name' => pht('Active'),
      ),
      self::STATUS_DISABLED => array(
        'name' => pht('Disabled'),
      ),
    );
  }

  public function getSortVector() {
    // Sort the primary number first, then active numbers, then disabled
    // numbers. In each group, sort from oldest to newest.
    return id(new PhutilSortVector())
      ->addInt($this->getIsPrimary() ? 0 : 1)
      ->addInt($this->isDisabled() ? 1 : 0)
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


/* -(  PhabricatorEditEngineMFAInterface  )---------------------------------- */


  public function newEditEngineMFAEngine() {
    return new PhabricatorAuthContactNumberMFAEngine();
  }

}
