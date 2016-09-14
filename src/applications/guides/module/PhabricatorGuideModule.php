<?php

abstract class PhabricatorGuideModule extends Phobject {

  abstract public function getModuleKey();
  abstract public function getModuleName();
  abstract public function getModulePosition();
  abstract public function getIsModuleEnabled();
  abstract public function renderModuleStatus(AphrontRequest $request);

  final public static function getAllModules() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getModuleKey')
      ->setSortMethod('getModulePosition')
      ->execute();
  }

  final public static function getEnabledModules() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getModuleKey')
      ->setSortMethod('getModulePosition')
      ->setFilterMethod('getIsModuleEnabled')
      ->execute();
  }

}
