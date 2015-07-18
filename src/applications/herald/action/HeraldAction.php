<?php

abstract class HeraldAction extends Phobject {

  private $adapter;
  private $viewer;
  private $applyLog = array();

  const STANDARD_NONE = 'standard.none';
  const STANDARD_PHID_LIST = 'standard.phid.list';

  abstract public function getHeraldActionName();
  abstract public function supportsObject($object);
  abstract public function supportsRuleType($rule_type);
  abstract public function applyEffect($object, HeraldEffect $effect);
  abstract public function renderActionEffectDescription($type, $data);

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
          ->setKey($this->getHeraldActionName())
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
    try {
      $type = $this->getHeraldActionStandardType();
    } catch (PhutilMethodNotImplementedException $ex) {
      return $value;
    }

    switch ($type) {
      case self::STANDARD_PHID_LIST:
        return array_keys($value);
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

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
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
    if (!is_string($type)) {
      throw new Exception(
        pht(
          'Effect type passed to "%s" must be a scalar string.',
          'logEffect()'));
    }

    $this->applyLog[] = array(
      'type' => $type,
      'data' => $data,
    );

    return $this;
  }

  final public function getApplyTranscript(HeraldEffect $effect) {
    $context = $this->applyLog;
    $this->applyLog = array();
    return new HeraldApplyTranscript($effect, true, $context);
  }

  protected function getActionEffectMap() {
    throw new PhutilMethodNotImplementedException();
  }

  private function getActionEffectSpec($type) {
    $map = $this->getActionEffectMap();
    return idx($map, $type, array());
  }

  public function renderActionEffectIcon($type, $data) {
    $map = $this->getActionEffectSpec($type);
    return idx($map, 'icon');
  }

  public function renderActionEffectColor($type, $data) {
    $map = $this->getActionEffectSpec($type);
    return idx($map, 'color');
  }

  public function renderActionEffectName($type, $data) {
    $map = $this->getActionEffectSpec($type);
    return idx($map, 'name');
  }

  protected function renderHandleList($phids) {
    if (!is_array($phids)) {
      return pht('(Invalid List)');
    }

    return $this->getViewer()
      ->renderHandleList($phids)
      ->setAsInline(true)
      ->render();
  }

}
