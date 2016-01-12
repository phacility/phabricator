<?php

final class PhabricatorProfilePanelConfiguration
  extends PhabricatorSearchDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface {

  protected $profilePHID;
  protected $panelKey;
  protected $builtinKey;
  protected $panelOrder;
  protected $isDisabled;
  protected $panelProperties = array();

  private $profileObject = self::ATTACHABLE;
  private $panel = self::ATTACHABLE;

  public static function initializeNewPanelConfiguration(
    PhabricatorProfilePanelInterface $profile_object,
    PhabricatorProfilePanel $panel) {

    return id(new self())
      ->setProfilePHID($profile_object->getPHID())
      ->setPanelKey($panel->getPanelKey())
      ->setIsDisabled(0)
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
        'builtinKey' => 'text64',
        'panelOrder' => 'uint32',
        'isDisabled' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_profile' => array(
          'columns' => array('profilePHID', 'panelOrder'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function attachPanel(PhabricatorProfilePanel $panel) {
    $this->panel = $panel;
    return $this;
  }

  public function getPanel() {
    return $this->assertAttached($this->panel);
  }

  public function attachProfileObject(
    PhabricatorProfilePanelInterface $profile_object) {
    $this->profileObject = $profile_object;
    return $this;
  }

  public function getProfileObject() {
    return $this->assertAttached($this->profileObject);
  }

  public function buildNavigationMenuItems() {
    return $this->getPanel()->buildNavigationMenuItems($this);
  }

  public function setPanelProperty($key, $value) {
    $this->panelProperties[$key] = $value;
    return $this;
  }

  public function getPanelProperty($key, $default = null) {
    return idx($this->panelProperties, $key, $default);
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


  public function describeAutomaticCapability($capability) {
    return null;
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

}
