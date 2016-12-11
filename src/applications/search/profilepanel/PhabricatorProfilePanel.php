<?php

abstract class PhabricatorProfilePanel extends Phobject {

  private $viewer;

  final public function buildNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $this->newNavigationMenuItems($config);
  }

  abstract protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config);

  public function getPanelTypeIcon() {
    return null;
  }

  abstract public function getPanelTypeName();

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

  public function canHidePanel(
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

  final public function getPanelKey() {
    return $this->getPhobjectClassConstant('PANELKEY');
  }

  final public static function getAllPanels() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getPanelKey')
      ->execute();
  }

  protected function newItem() {
    return new PHUIListItemView();
  }

}
