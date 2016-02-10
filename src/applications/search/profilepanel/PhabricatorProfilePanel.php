<?php

abstract class PhabricatorProfilePanel extends Phobject {

  private $viewer;

  final public function buildNavigationMenuItems(
    PhabricatorProfilePanelConfiguration $config) {
    return $this->newNavigationMenuItems($config);
  }

  abstract protected function newNavigationMenuItems(
    PhabricatorProfilePanelConfiguration $config);

  public function getPanelTypeIcon() {
    return null;
  }

  abstract public function getPanelTypeName();

  abstract public function getDisplayName(
    PhabricatorProfilePanelConfiguration $config);

  public function buildEditEngineFields(
    PhabricatorProfilePanelConfiguration $config) {
    return array();
  }

  public function canAddToObject($object) {
    return false;
  }

  public function shouldEnableForObject($object) {
    return true;
  }

  public function canHidePanel(
    PhabricatorProfilePanelConfiguration $config) {
    return true;
  }

  public function canMakeDefault(
    PhabricatorProfilePanelConfiguration $config) {
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
