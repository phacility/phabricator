<?php

abstract class HeraldField extends Phobject {

  private $adapter;

  const STANDARD_LIST = 'standard.list';
  const STANDARD_BOOL = 'standard.bool';
  const STANDARD_PHID = 'standard.phid';

  abstract public function getHeraldFieldName();
  abstract public function getHeraldFieldValue($object);

  public function getHeraldFieldConditions() {
    switch ($this->getHeraldFieldStandardConditions()) {
      case self::STANDARD_LIST:
        return array(
          HeraldAdapter::CONDITION_INCLUDE_ALL,
          HeraldAdapter::CONDITION_INCLUDE_ANY,
          HeraldAdapter::CONDITION_INCLUDE_NONE,
          HeraldAdapter::CONDITION_EXISTS,
          HeraldAdapter::CONDITION_NOT_EXISTS,
        );
      case self::STANDARD_BOOL:
        return array(
          HeraldAdapter::CONDITION_IS_TRUE,
          HeraldAdapter::CONDITION_IS_FALSE,
        );
      case self::STANDARD_PHID:
        return array(
          HeraldAdapter::CONDITION_IS_ANY,
          HeraldAdapter::CONDITION_IS_NOT_ANY,
        );

    }

    throw new Exception(pht('Unknown standard condition set.'));
  }

  protected function getHeraldFieldStandardConditions() {
    throw new PhutilMethodNotImplementedException();
  }

  abstract public function getHeraldFieldValueType($condition);

  abstract public function supportsObject($object);

  public function getFieldsForObject($object) {
    return array($this->getFieldConstant() => $this);
  }

  final public function setAdapter(HeraldAdapter $adapter) {
    $this->adapter = $adapter;
    return $this;
  }

  final public function getAdapter() {
    return $this->adapter;
  }

  final public function getFieldConstant() {
    $class = new ReflectionClass($this);

    $const = $class->getConstant('FIELDCONST');
    if ($const === false) {
      throw new Exception(
        pht(
          '"%s" class "%s" must define a "%s" property.',
          __CLASS__,
          get_class($this),
          'FIELDCONST'));
    }

    $limit = self::getFieldConstantByteLimit();
    if (!is_string($const) || (strlen($const) > $limit)) {
      throw new Exception(
        pht(
          '"%s" class "%s" has an invalid "%s" property. Field constants '.
          'must be strings and no more than %s bytes in length.',
          __CLASS__,
          get_class($this),
          'FIELDCONST',
          new PhutilNumber($limit)));
    }

    return $const;
  }

  final public static function getFieldConstantByteLimit() {
    return 64;
  }

  final public static function getAllFields() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getFieldConstant')
      ->execute();
  }

}
