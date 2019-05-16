<?php

final class PhabricatorFactChart
  extends PhabricatorFactDAO
  implements PhabricatorPolicyInterface {

  protected $chartKey;
  protected $chartParameters = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'chartParameters' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'chartKey' => 'bytes12',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_chart' => array(
          'columns' => array('chartKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function setChartParameter($key, $value) {
    $this->chartParameters[$key] = $value;
    return $this;
  }

  public function getChartParameter($key, $default = null) {
    return idx($this->chartParameters, $key, $default);
  }

  public function save() {
    if ($this->getID()) {
      throw new Exception(
        pht(
          'Chart configurations are not mutable. You can not update or '.
          'overwrite an existing chart configuration.'));
    }

    $digest = serialize($this->chartParameters);
    $digest = PhabricatorHash::digestForIndex($digest);

    $this->chartKey = $digest;

    return parent::save();
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


}
