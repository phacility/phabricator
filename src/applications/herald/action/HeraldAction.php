<?php

abstract class HeraldAction extends Phobject {

  private $adapter;
  private $applyLog = array();

  const STANDARD_NONE = 'standard.none';
  const STANDARD_PHID_LIST = 'standard.phid.list';

  abstract public function getHeraldActionName();
  abstract public function supportsObject($object);
  abstract public function supportsRuleType($rule_type);
  abstract public function applyEffect($object, HeraldEffect $effect);

  public function getActionGroupKey() {
    return null;
  }

  public function getActionsForObject($object) {
    return array($this->getActionConstant() => $this);
  }

  protected function getDatasource() {
    throw new PhutilMethodNotImplementedException();
  }

  protected function getDatasourceValueMap() {
    return null;
  }

  public function getHeraldActionStandardType() {
    throw new PhutilMethodNotImplementedException();
  }

  public function getHeraldActionValueType() {
    switch ($this->getHeraldActionStandardType()) {
      case self::STANDARD_NONE:
        return new HeraldEmptyFieldValue();
      case self::STANDARD_PHID_LIST:
        $tokenizer = id(new HeraldTokenizerFieldValue())
          ->setKey($this->getHeraldFieldName())
          ->setDatasource($this->getDatasource());

        $value_map = $this->getDatasourceValueMap();
        if ($value_map !== null) {
          $tokenizer->setValueMap($value_map);
        }

        return $tokenizer;
    }

    throw new PhutilMethodNotImplementedException();
  }

  public function willSaveActionValue($value) {
    return $value;
  }

  final public function setAdapter(HeraldAdapter $adapter) {
    $this->adapter = $adapter;
    return $this;
  }

  final public function getAdapter() {
    return $this->adapter;
  }

  final public function getActionConstant() {
    $class = new ReflectionClass($this);

    $const = $class->getConstant('ACTIONCONST');
    if ($const === false) {
      throw new Exception(
        pht(
          '"%s" class "%s" must define a "%s" property.',
          __CLASS__,
          get_class($this),
          'ACTIONCONST'));
    }

    $limit = self::getActionConstantByteLimit();
    if (!is_string($const) || (strlen($const) > $limit)) {
      throw new Exception(
        pht(
          '"%s" class "%s" has an invalid "%s" property. Action constants '.
          'must be strings and no more than %s bytes in length.',
          __CLASS__,
          get_class($this),
          'ACTIONCONST',
          new PhutilNumber($limit)));
    }

    return $const;
  }

  final public static function getActionConstantByteLimit() {
    return 64;
  }

  final public static function getAllActions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getActionConstant')
      ->execute();
  }

  protected function logEffect($type, $data = null) {
    return;
  }

  final public function getApplyTranscript(HeraldEffect $effect) {
    $context = 'v2/'.phutil_json_encode($this->applyLog);
    $this->applyLog = array();
    return new HeraldApplyTranscript($effect, true, $context);
  }

}
