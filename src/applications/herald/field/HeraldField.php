<?php

abstract class HeraldField extends Phobject {

  private $adapter;

  abstract public function getHeraldFieldName();
  abstract public function getHeraldFieldValue($object);
  abstract public function getHeraldFieldConditions();
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

    if (!is_string($const) || (strlen($const) > 32)) {
      throw new Exception(
        pht(
          '"%s" class "%s" has an invalid "%s" property. Field constants '.
          'must be strings and no more than 32 bytes in length.',
          __CLASS__,
          get_class($this),
          'FIELDCONST'));
    }

    return $const;
  }

  final public static function getAllFields() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getFieldConstant')
      ->execute();
  }

}
