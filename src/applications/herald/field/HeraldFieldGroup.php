<?php

abstract class HeraldFieldGroup extends HeraldGroup {

  final public function getGroupKey() {
    return $this->getPhobjectClassConstant('FIELDGROUPKEY');
  }

  final public static function getAllFieldGroups() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getGroupKey')
      ->setSortMethod('getSortKey')
      ->execute();
  }
}
