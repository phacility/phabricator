<?php

abstract class HeraldFieldGroup extends Phobject {

  abstract public function getGroupLabel();

  protected function getGroupOrder() {
    return 1000;
  }

  final public function getGroupKey() {
    $class = new ReflectionClass($this);

    $const = $class->getConstant('FIELDGROUPKEY');
    if ($const === false) {
      throw new Exception(
        pht(
          '"%s" class "%s" must define a "%s" property.',
          __CLASS__,
          get_class($this),
          'FIELDCONST'));
    }

    return $const;
  }

  public function getSortKey() {
    return sprintf('A%08d%s', $this->getGroupOrder(), $this->getGroupLabel());
  }

  final public static function getAllFieldGroups() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getGroupKey')
      ->setSortMethod('getSortKey')
      ->execute();
  }
}
