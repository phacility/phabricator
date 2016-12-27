<?php

abstract class PhabricatorProfileMenuItem extends Phobject {

  private $viewer;

  final public function buildNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $this->newNavigationMenuItems($config);
  }

  abstract protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config);

  public function willBuildNavigationItems(array $items) {}

  public function getMenuItemTypeIcon() {
    return null;
  }

  abstract public function getMenuItemTypeName();

  abstract public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config);

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array();
  }

  public function canAddToObject($object) {
    return false;
  }

  public function shouldEnableForObject($object) {
    return true;
  }

  public function canHideMenuItem(
    PhabricatorProfileMenuItemConfiguration $config) {
    return true;
  }

  public function canMakeDefault(
    PhabricatorProfileMenuItemConfiguration $config) {
    return false;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  final public function getMenuItemKey() {
    return $this->getPhobjectClassConstant('MENUITEMKEY');
  }

  final public static function getAllMenuItems() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getMenuItemKey')
      ->execute();
  }

  protected function newItem() {
    return new PHUIListItemView();
  }

}
