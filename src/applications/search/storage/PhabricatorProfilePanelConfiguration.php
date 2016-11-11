<?php

final class PhabricatorProfilePanelConfiguration
  extends PhabricatorSearchDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface,
    PhabricatorApplicationTransactionInterface {

  protected $profilePHID;
  protected $panelKey;
  protected $builtinKey;
  protected $panelOrder;
  protected $visibility;
  protected $panelProperties = array();

  private $profileObject = self::ATTACHABLE;
  private $panel = self::ATTACHABLE;

  const VISIBILITY_DEFAULT = 'default';
  const VISIBILITY_VISIBLE = 'visible';
  const VISIBILITY_DISABLED = 'disabled';

  public static function initializeNewBuiltin() {
    return id(new self())
      ->setVisibility(self::VISIBILITY_VISIBLE);
  }

  public static function initializeNewPanelConfiguration(
    $profile_object,
    PhabricatorProfilePanel $panel) {

    return self::initializeNewBuiltin()
      ->setProfilePHID($profile_object->getPHID())
      ->setPanelKey($panel->getPanelKey())
      ->attachPanel($panel)
      ->attachProfileObject($profile_object);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'panelProperties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'panelKey' => 'text64',
        'builtinKey' => 'text64?',
        'panelOrder' => 'uint32?',
        'visibility' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_profile' => array(
          'columns' => array('profilePHID', 'panelOrder'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorProfilePanelPHIDType::TYPECONST);
  }

  public function attachPanel(PhabricatorProfilePanel $panel) {
    $this->panel = $panel;
    return $this;
  }

  public function getPanel() {
    return $this->assertAttached($this->panel);
  }

  public function attachProfileObject($profile_object) {
    $this->profileObject = $profile_object;
    return $this;
  }

  public function getProfileObject() {
    return $this->assertAttached($this->profileObject);
  }

  public function setPanelProperty($key, $value) {
    $this->panelProperties[$key] = $value;
    return $this;
  }

  public function getPanelProperty($key, $default = null) {
    return idx($this->panelProperties, $key, $default);
  }

  public function buildNavigationMenuItems() {
    return $this->getPanel()->buildNavigationMenuItems($this);
  }

  public function getPanelTypeName() {
    return $this->getPanel()->getPanelTypeName();
  }

  public function getDisplayName() {
    return $this->getPanel()->getDisplayName($this);
  }

  public function canMakeDefault() {
    return $this->getPanel()->canMakeDefault($this);
  }

  public function canHidePanel() {
    return $this->getPanel()->canHidePanel($this);
  }

  public function shouldEnableForObject($object) {
    return $this->getPanel()->shouldEnableForObject($object);
  }

  public function getSortKey() {
    $order = $this->getPanelOrder();
    if ($order === null) {
      $order = 'Z';
    } else {
      $order = sprintf('%020d', $order);
    }

    return sprintf(
      '~%s%020d',
      $order,
      $this->getID());
  }

  public function isDisabled() {
    if (!$this->canHidePanel()) {
      return false;
    }
    return ($this->getVisibility() === self::VISIBILITY_DISABLED);
  }

  public function isDefault() {
    return ($this->getVisibility() === self::VISIBILITY_DEFAULT);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }


  public function getPolicy($capability) {
    return PhabricatorPolicies::getMostOpenPolicy();
  }


  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getProfileObject()->hasAutomaticCapability(
      $capability,
      $viewer);
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    return array(
      array(
        $this->getProfileObject(),
        $capability,
      ),
    );
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorProfilePanelEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorProfilePanelConfigurationTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }

}
