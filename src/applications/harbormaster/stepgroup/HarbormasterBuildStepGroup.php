<?php

abstract class HarbormasterBuildStepGroup extends Phobject {

  abstract public function getGroupName();
  abstract public function getGroupOrder();

  public function isEnabled() {
    return true;
  }

  public function shouldShowIfEmpty() {
    return true;
  }

  final public function getGroupKey() {
    return $this->getPhobjectClassConstant('GROUPKEY');
  }

  final public static function getAllGroups() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getGroupKey')
      ->setSortMethod('getGroupOrder')
      ->execute();
  }

  final public static function getAllEnabledGroups() {
    $groups = self::getAllGroups();

    foreach ($groups as $key => $group) {
      if (!$group->isEnabled()) {
        unset($groups[$key]);
      }
    }

    return $groups;
  }

}
