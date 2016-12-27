<?php

final class PhabricatorProfileMenuItemConfiguration
  extends PhabricatorSearchDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface,
    PhabricatorApplicationTransactionInterface {

  protected $profilePHID;
  protected $menuItemKey;
  protected $builtinKey;
  protected $menuItemOrder;
  protected $visibility;
  protected $menuItemProperties = array();

  private $profileObject = self::ATTACHABLE;
  private $menuItem = self::ATTACHABLE;

  const VISIBILITY_DEFAULT = 'default';
  const VISIBILITY_VISIBLE = 'visible';
  const VISIBILITY_DISABLED = 'disabled';

  public function getTableName() {
    // For now, this class uses an older table name.
    return 'search_profilepanelconfiguration';
  }

  public static function initializeNewBuiltin() {
    return id(new self())
      ->setVisibility(self::VISIBILITY_VISIBLE);
  }

  public static function initializeNewItem(
    $profile_object,
    PhabricatorProfileMenuItem $item) {

    return self::initializeNewBuiltin()
      ->setProfilePHID($profile_object->getPHID())
      ->setMenuItemKey($item->getMenuItemKey())
      ->attachMenuItem($item)
      ->attachProfileObject($profile_object);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'menuItemProperties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'menuItemKey' => 'text64',
        'builtinKey' => 'text64?',
        'menuItemOrder' => 'uint32?',
        'visibility' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_profile' => array(
          'columns' => array('profilePHID', 'menuItemOrder'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorProfileMenuItemPHIDType::TYPECONST);
  }

  public function attachMenuItem(PhabricatorProfileMenuItem $item) {
    $this->menuItem = $item;
    return $this;
  }

  public function getMenuItem() {
    return $this->assertAttached($this->menuItem);
  }

  public function attachProfileObject($profile_object) {
    $this->profileObject = $profile_object;
    return $this;
  }

  public function getProfileObject() {
    return $this->assertAttached($this->profileObject);
  }

  public function setMenuItemProperty($key, $value) {
    $this->menuItemProperties[$key] = $value;
    return $this;
  }

  public function getMenuItemProperty($key, $default = null) {
    return idx($this->menuItemProperties, $key, $default);
  }

  public function buildNavigationMenuItems() {
    return $this->getMenuItem()->buildNavigationMenuItems($this);
  }

  public function getMenuItemTypeName() {
    return $this->getMenuItem()->getMenuItemTypeName();
  }

  public function getDisplayName() {
    return $this->getMenuItem()->getDisplayName($this);
  }

  public function canMakeDefault() {
    return $this->getMenuItem()->canMakeDefault($this);
  }

  public function canHideMenuItem() {
    return $this->getMenuItem()->canHideMenuItem($this);
  }

  public function shouldEnableForObject($object) {
    return $this->getMenuItem()->shouldEnableForObject($object);
  }

  public function willBuildNavigationItems(array $items) {
    return $this->getMenuItem()->willBuildNavigationItems($items);
  }

  public function getSortKey() {
    $order = $this->getMenuItemOrder();
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
    if (!$this->canHideMenuItem()) {
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
    return new PhabricatorProfileMenuEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorProfileMenuItemConfigurationTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }

}
