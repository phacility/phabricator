<?php

final class PhabricatorCalendarExport extends PhabricatorCalendarDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorDestructibleInterface {

  protected $name;
  protected $authorPHID;
  protected $policyMode;
  protected $queryKey;
  protected $secretKey;
  protected $isDisabled = 0;

  const MODE_PUBLIC = 'public';
  const MODE_PRIVATE = 'private';

  public static function initializeNewCalendarExport(PhabricatorUser $actor) {
    return id(new self())
      ->setAuthorPHID($actor->getPHID())
      ->setPolicyMode(self::MODE_PRIVATE)
      ->setIsDisabled(0);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text',
        'policyMode' => 'text64',
        'queryKey' => 'text64',
        'secretKey' => 'bytes20',
        'isDisabled' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_author' => array(
          'columns' => array('authorPHID'),
        ),
        'key_secret' => array(
          'columns' => array('secretKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorCalendarExportPHIDType::TYPECONST;
  }

  public function save() {
    if (!$this->getSecretKey()) {
      $this->setSecretKey(Filesystem::readRandomCharacters(20));
    }

    return parent::save();
  }

  public function getURI() {
    $id = $this->getID();
    return "/calendar/export/{$id}/";
  }

  private static function getPolicyModeMap() {
    return array(
      self::MODE_PUBLIC => array(
        'name' => pht('Public'),
      ),
      self::MODE_PRIVATE => array(
        'name' => pht('Private'),
      ),
    );
  }

  private static function getPolicyModeSpec($const) {
    return idx(self::getPolicyModeMap(), $const, array());
  }

  public static function getPolicyModeName($const) {
    $map = self::getPolicyModeSpec($const);
    return idx($map, 'name', $const);
  }

  public static function getPolicyModes() {
    return array_keys(self::getPolicyModeMap());
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getAuthorPHID();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorCalendarExportEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorCalendarExportTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $this->delete();
  }

}
