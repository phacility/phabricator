<?php

final class PhabricatorSavedQuery extends PhabricatorSearchDAO
  implements PhabricatorPolicyInterface {

  protected $parameters = array();
  protected $queryKey;
  protected $engineClassName;

  private $parameterMap = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'parameters' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'engineClassName' => 'text255',
        'queryKey' => 'text12',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_queryKey' => array(
          'columns' => array('queryKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function setParameter($key, $value) {
    $this->parameters[$key] = $value;
    return $this;
  }

  public function getParameter($key, $default = null) {
    return idx($this->parameters, $key, $default);
  }

  public function save() {
    if ($this->getEngineClassName() === null) {
      throw new Exception(pht('Engine class is null.'));
    }

    // Instantiate the engine to make sure it's valid.
    $this->newEngine();

    $serial = $this->getEngineClassName().serialize($this->parameters);
    $this->queryKey = PhabricatorHash::digestForIndex($serial);

    return parent::save();
  }

  public function newEngine() {
    return newv($this->getEngineClassName(), array());
  }

  public function attachParameterMap(array $map) {
    $this->parameterMap = $map;
    return $this;
  }

  public function getEvaluatedParameter($key) {
    return $this->assertAttachedKey($this->parameterMap, $key);
  }

  public function newCopy() {
    return id(new self())
      ->setParameters($this->getParameters())
      ->setQueryKey(null)
      ->setEngineClassName($this->getEngineClassName());
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_PUBLIC;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

}
