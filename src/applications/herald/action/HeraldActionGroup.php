<?php

abstract class HeraldActionGroup extends HeraldGroup {

  final public function getGroupKey() {
    return $this->getPhobjectClassConstant('ACTIONGROUPKEY');
  }

  final public static function getAllActionGroups() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getGroupKey')
      ->setSortMethod('getSortKey')
      ->execute();
  }
}
