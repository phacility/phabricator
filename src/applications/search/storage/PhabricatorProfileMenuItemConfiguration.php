<?php

final class PhabricatorProfileMenuItemConfiguration
  extends PhabricatorSearchDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorIndexableInterface {

  protected $profilePHID;
  protected $menuItemKey;
  protected $builtinKey;
  protected $menuItemOrder;
  protected $visibility;
  protected $customPHID;
  protected $menuItemProperties = array();

  private $profileObject = self::ATTACHABLE;
  private $menuItem = self::ATTACHABLE;
  private $isHeadItem = false;
  private $isTailItem = false;

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
    PhabricatorProfileMenuItem $item,
    $custom_phid) {

    return self::initializeNewBuiltin()
      ->setProfilePHID($profile_object->getPHID())
      ->setMenuItemKey($item->getMenuItemKey())
      ->attachMenuItem($item)
      ->attachProfileObject($profile_object)
      ->setCustomPHID($custom_phid);
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
        'customPHID' => 'phid?',
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

  public function willGetMenuItemViewList(array $items) {
    return $this->getMenuItem()->willGetMenuItemViewList($items);
  }

  public function getMenuItemViewList() {
    return $this->getMenuItem()->getMenuItemViewList($this);
  }

  public function validateTransactions(array $map) {
    $item = $this->getMenuItem();

    $fields = $item->buildEditEngineFields($this);
    $errors = array();
    foreach ($fields as $field) {
      $field_key = $field->getKey();

      $xactions = idx($map, $field_key, array());
      $value = $this->getMenuItemProperty($field_key);

      $field_errors = $item->validateTransactions(
        $this,
        $field_key,
        $value,
        $xactions);
      foreach ($field_errors as $error) {
        $errors[] = $error;
      }
    }

    return $errors;
  }

  public function getSortVector() {
    // Sort custom items above global items.
    if ($this->getCustomPHID()) {
      $is_global = 0;
    } else {
      $is_global = 1;
    }

    // Sort "head" items above other items and "tail" items after other items.
    if ($this->getIsHeadItem()) {
      $force_position = 0;
    } else if ($this->getIsTailItem()) {
      $force_position = 2;
    } else {
      $force_position = 1;
    }

    // Sort items with an explicit order above items without an explicit order,
    // so any newly created builtins go to the bottom.
    $order = $this->getMenuItemOrder();
    if ($order !== null) {
      $has_order = 0;
    } else {
      $has_order = 1;
    }

    return id(new PhutilSortVector())
      ->addInt($is_global)
      ->addInt($force_position)
      ->addInt($has_order)
      ->addInt((int)$order)
      ->addInt((int)$this->getID());
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

  public function getItemIdentifier() {
    $id = $this->getID();

    if ($id) {
      return (int)$id;
    }

    return $this->getBuiltinKey();
  }

  public function getDefaultMenuItemKey() {
    if ($this->getBuiltinKey()) {
      return $this->getBuiltinKey();
    }

    return $this->getPHID();
  }

  public function newPageContent() {
    return $this->getMenuItem()->newPageContent($this);
  }

  public function setIsHeadItem($is_head_item) {
    $this->isHeadItem = $is_head_item;
    return $this;
  }

  public function getIsHeadItem() {
    return $this->isHeadItem;
  }

  public function setIsTailItem($is_tail_item) {
    $this->isTailItem = $is_tail_item;
    return $this;
  }

  public function getIsTailItem() {
    return $this->isTailItem;
  }

  public function matchesIdentifier($identifier) {
    if (!strlen($identifier)) {
      return false;
    }

    if (ctype_digit($identifier)) {
      if ((int)$this->getID() === (int)$identifier) {
        return true;
      }
    }

    if ((string)$this->getBuiltinKey() === (string)$identifier) {
      return true;
    }

    return false;
  }

  public function getAffectedObjectPHIDs() {
    return $this->getMenuItem()->getAffectedObjectPHIDs($this);
  }

  public function getProfileMenuTypeDescription() {
    $profile_phid = $this->getProfilePHID();

    $home_phid = id(new PhabricatorHomeApplication())->getPHID();
    if ($profile_phid === $home_phid) {
      return pht('Home Menu');
    }

    $favorites_phid = id(new PhabricatorFavoritesApplication())->getPHID();
    if ($profile_phid === $favorites_phid) {
      return pht('Favorites Menu');
    }

    switch (phid_get_type($profile_phid)) {
      case PhabricatorProjectProjectPHIDType::TYPECONST:
        return pht('Project Menu');
      case PhabricatorDashboardPortalPHIDType::TYPECONST:
        return pht('Portal Menu');
    }

    return pht('Profile Menu');
  }

  public function newUsageSortVector() {
    // Used to sort items in contexts where we're showing the usage of an
    // object in menus, like "Dashboard Used By" on Dashboard pages.

    // Sort usage as a custom item after usage as a global item.
    if ($this->getCustomPHID()) {
      $is_personal = 1;
    } else {
      $is_personal = 0;
    }

    return id(new PhutilSortVector())
      ->addInt($is_personal)
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
    return PhabricatorPolicies::getMostOpenPolicy();
  }


  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getProfileObject()->hasAutomaticCapability(
      $capability,
      $viewer);
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    // If this is an item with a custom PHID (like a personal menu item),
    // we only require that the user can edit the corresponding custom
    // object (usually their own user profile), not the object that the
    // menu appears on (which may be an Application like Favorites or Home).
    if ($capability == PhabricatorPolicyCapability::CAN_EDIT) {
      if ($this->getCustomPHID()) {
        return array(
          array(
            $this->getCustomPHID(),
            $capability,
          ),
        );
      }
    }

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

  public function getApplicationTransactionTemplate() {
    return new PhabricatorProfileMenuItemConfigurationTransaction();
  }

}
