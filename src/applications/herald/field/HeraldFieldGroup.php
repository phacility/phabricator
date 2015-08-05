<?php

abstract class HeraldFieldGroup extends HeraldGroup {

  final public function getGroupKey() {
    $class = new ReflectionClass($this);

    $const = $class->getConstant('FIELDGROUPKEY');
    if ($const === false) {
      throw new Exception(
        pht(
          '"%s" class "%s" must define a "%s" property.',
          __CLASS__,
          get_class($this),
          'FIELDGROUPKEY'));
    }

    return $const;
  }

  final public static function getAllFieldGroups() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getGroupKey')
      ->setSortMethod('getSortKey')
      ->execute();
  }
}
