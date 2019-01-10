<?php

abstract class DiffusionRepositoryManagementPanelGroup
  extends Phobject {

  final public function getManagementPanelGroupKey() {
    return $this->getPhobjectClassConstant('PANELGROUPKEY');
  }

  abstract public function getManagementPanelGroupOrder();
  abstract public function getManagementPanelGroupLabel();

  public static function getAllPanelGroups() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getManagementPanelGroupKey')
      ->setSortMethod('getManagementPanelGroupOrder')
      ->execute();
  }

}
