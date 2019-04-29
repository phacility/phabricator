<?php

final class PhabricatorFactChart
  extends PhabricatorFactDAO
  implements PhabricatorPolicyInterface {

  protected $chartKey;
  protected $chartParameters = array();

  private $datasets;

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

  public function newChartKey() {
    $digest = serialize($this->chartParameters);
    $digest = PhabricatorHash::digestForIndex($digest);
    return $digest;
  }

  public function save() {
    if ($this->getID()) {
      throw new Exception(
        pht(
          'Chart configurations are not mutable. You can not update or '.
          'overwrite an existing chart configuration.'));
    }

    $this->chartKey = $this->newChartKey();

    return parent::save();
  }

  public function setDatasets(array $datasets) {
    assert_instances_of($datasets, 'PhabricatorChartDataset');

    $dataset_list = array();
    foreach ($datasets as $dataset) {
      $dataset_list[] = $dataset->toDictionary();
    }

    $this->setChartParameter('datasets', $dataset_list);
    $this->datasets = null;

    return $this;
  }

  public function getDatasets() {
    if ($this->datasets === null) {
      $this->datasets = $this->newDatasets();
    }
    return $this->datasets;
  }

  private function newDatasets() {
    $datasets = $this->getChartParameter('datasets', array());

    foreach ($datasets as $key => $dataset) {
      $datasets[$key] = PhabricatorChartDataset::newFromDictionary($dataset);
    }

    return $datasets;
  }

  public function getURI() {
    return urisprintf('/fact/chart/%s/', $this->getChartKey());
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
