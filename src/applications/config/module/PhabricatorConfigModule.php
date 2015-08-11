<?php

abstract class PhabricatorConfigModule extends Phobject {

  abstract public function getModuleKey();
  abstract public function getModuleName();
  abstract public function renderModuleStatus(AphrontRequest $request);

  final public static function getAllModules() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getModuleKey')
      ->setSortMethod('getModuleName')
      ->execute();
  }

}
