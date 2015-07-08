<?php

abstract class HeraldField extends Phobject {

  private $adapter;

  const STANDARD_LIST = 'standard.list';
  const STANDARD_BOOL = 'standard.bool';
  const STANDARD_TEXT = 'standard.text';
  const STANDARD_TEXT_LIST = 'standard.text.list';
  const STANDARD_TEXT_MAP = 'standard.text.map';
  const STANDARD_PHID = 'standard.phid';
  const STANDARD_PHID_BOOL = 'standard.phid.bool';
  const STANDARD_PHID_NULLABLE = 'standard.phid.nullable';

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
      case self::STANDARD_TEXT:
        return array(
          HeraldAdapter::CONDITION_CONTAINS,
          HeraldAdapter::CONDITION_NOT_CONTAINS,
          HeraldAdapter::CONDITION_IS,
          HeraldAdapter::CONDITION_IS_NOT,
          HeraldAdapter::CONDITION_REGEXP,
        );
      case self::STANDARD_PHID:
        return array(
          HeraldAdapter::CONDITION_IS_ANY,
          HeraldAdapter::CONDITION_IS_NOT_ANY,
        );
      case self::STANDARD_PHID_BOOL:
        return array(
          HeraldAdapter::CONDITION_EXISTS,
          HeraldAdapter::CONDITION_NOT_EXISTS,
        );
      case self::STANDARD_PHID_NULLABLE:
        return array(
          HeraldAdapter::CONDITION_IS_ANY,
          HeraldAdapter::CONDITION_IS_NOT_ANY,
          HeraldAdapter::CONDITION_EXISTS,
          HeraldAdapter::CONDITION_NOT_EXISTS,
        );
      case self::STANDARD_TEXT_LIST:
        return array(
          HeraldAdapter::CONDITION_CONTAINS,
          HeraldAdapter::CONDITION_REGEXP,
        );
      case self::STANDARD_TEXT_MAP:
        return array(
          HeraldAdapter::CONDITION_CONTAINS,
          HeraldAdapter::CONDITION_REGEXP,
          HeraldAdapter::CONDITION_REGEXP_PAIR,
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

  public function renderConditionValue(
    PhabricatorUser $viewer,
    $value) {

    // TODO: While this is less of a mess than it used to be, it would still
    // be nice to push this down into individual fields better eventually and
    // stop guessing which values are PHIDs and which aren't.

    if (!is_array($value)) {
      return $value;
    }

    $type_unknown = PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN;

    foreach ($value as $key => $val) {
      if (is_string($val)) {
        if (phid_get_type($val) !== $type_unknown) {
          $value[$key] = $viewer->renderHandle($val);
        }
      }
    }

    return phutil_implode_html(', ', $value);
  }

  public function getEditorValue(
    PhabricatorUser $viewer,
    $value) {

    // TODO: This should be better structured and pushed down into individual
    // fields. As it is used to manually build tokenizer tokens, it can
    // probably be removed entirely.

    if (is_array($value)) {
      $handles = $viewer->loadHandles($value);
      $value_map = array();
      foreach ($value as $k => $phid) {
        $value_map[$phid] = $handles[$phid]->getName();
      }
      $value = $value_map;
    }

    return $value;
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
