<?php

abstract class PhabricatorChartDataset
  extends Phobject {

  private $functions;

  final public function getDatasetTypeKey() {
    return $this->getPhobjectClassConstant('DATASETKEY', 32);
  }

  final public function getFunctions() {
    return $this->functions;
  }

  final public function setFunctions(array $functions) {
    assert_instances_of($functions, 'PhabricatorComposeChartFunction');

    $this->functions = $functions;

    return $this;
  }

  final public static function getAllDatasetTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getDatasetTypeKey')
      ->execute();
  }

  final public static function newFromDictionary(array $map) {
    PhutilTypeSpec::checkMap(
      $map,
      array(
        'type' => 'string',
        'functions' => 'list<wild>',
      ));

    $types = self::getAllDatasetTypes();

    $dataset_type = $map['type'];
    if (!isset($types[$dataset_type])) {
      throw new Exception(
        pht(
          'Trying to construct a dataset of type "%s", but this type is '.
          'unknown. Supported types are: %s.',
          $dataset_type,
          implode(', ', array_keys($types))));
    }

    $dataset = id(clone $types[$dataset_type]);

    $functions = array();
    foreach ($map['functions'] as $map) {
      $functions[] = PhabricatorChartFunction::newFromDictionary($map);
    }
    $dataset->setFunctions($functions);

    return $dataset;
  }

  final public function toDictionary() {
    return array(
      'type' => $this->getDatasetTypeKey(),
      'functions' => mpull($this->getFunctions(), 'toDictionary'),
    );
  }

  final public function getChartDisplayData(
    PhabricatorChartDataQuery $data_query) {
    return $this->newChartDisplayData($data_query);
  }

  abstract protected function newChartDisplayData(
    PhabricatorChartDataQuery $data_query);


}
