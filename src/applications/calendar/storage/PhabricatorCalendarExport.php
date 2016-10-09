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
  const MODE_PRIVILEGED = 'privileged';

  public static function initializeNewCalendarExport(PhabricatorUser $actor) {
    return id(new self())
      ->setAuthorPHID($actor->getPHID())
      ->setPolicyMode(self::MODE_PRIVILEGED)
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
        'icon' => 'fa-globe',
        'name' => pht('Public'),
        'color' => 'bluegrey',
        'summary' => pht(
          'Export only public data.'),
        'description' => pht(
          'Only publicly available data is exported.'),
      ),
      self::MODE_PRIVILEGED => array(
        'icon' => 'fa-unlock-alt',
        'name' => pht('Privileged'),
        'color' => 'red',
        'summary' => pht(
          'Export private data.'),
        'description' => pht(
          'Anyone who knows the URI for this export can view all event '.
          'details as though they were logged in with your account.'),
      ),
    );
  }

  private static function getPolicyModeSpec($const) {
    return idx(self::getPolicyModeMap(), $const, array());
  }

  public static function getPolicyModeName($const) {
    $spec = self::getPolicyModeSpec($const);
    return idx($spec, 'name', $const);
  }

  public static function getPolicyModeIcon($const) {
    $spec = self::getPolicyModeSpec($const);
    return idx($spec, 'icon', $const);
  }

  public static function getPolicyModeColor($const) {
    $spec = self::getPolicyModeSpec($const);
    return idx($spec, 'color', $const);
  }

  public static function getPolicyModeSummary($const) {
    $spec = self::getPolicyModeSpec($const);
    return idx($spec, 'summary', $const);
  }

  public static function getPolicyModeDescription($const) {
    $spec = self::getPolicyModeSpec($const);
    return idx($spec, 'description', $const);
  }

  public static function getPolicyModes() {
    return array_keys(self::getPolicyModeMap());
  }

  public static function getAvailablePolicyModes() {
    $modes = array();

    if (PhabricatorEnv::getEnvConfig('policy.allow-public')) {
      $modes[] = self::MODE_PUBLIC;
    }

    $modes[] = self::MODE_PRIVILEGED;

    return $modes;
  }

  public function getICSFilename() {
    return PhabricatorSlug::normalizeProjectSlug($this->getName()).'.ics';
  }

  public function getICSURI() {
    $secret_key = $this->getSecretKey();
    $ics_name = $this->getICSFilename();
    return "/calendar/export/ics/{$secret_key}/{$ics_name}";
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
