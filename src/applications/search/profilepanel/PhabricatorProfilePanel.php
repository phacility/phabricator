<?php

abstract class PhabricatorProfilePanel extends Phobject {

  private $viewer;

  final public function buildNavigationMenuItems(
    PhabricatorProfilePanelConfiguration $config) {
    return $this->newNavigationMenuItems($config);
  }

  abstract protected function newNavigationMenuItems(
    PhabricatorProfilePanelConfiguration $config);

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

}
