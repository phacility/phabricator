<?php

abstract class HeraldActionGroup extends HeraldGroup {

  final public function getGroupKey() {
    $class = new ReflectionClass($this);

    $const = $class->getConstant('ACTIONGROUPKEY');
    if ($const === false) {
      throw new Exception(
        pht(
          '"%s" class "%s" must define a "%s" property.',
          __CLASS__,
          get_class($this),
          'ACTIONGROUPKEY'));
    }

    return $const;
  }

  final public static function getAllActionGroups() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getGroupKey')
      ->setSortMethod('getSortKey')
      ->execute();
  }
}
