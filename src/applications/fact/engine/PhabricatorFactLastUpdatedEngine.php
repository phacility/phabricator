<?php

/**
 * Engine that records the time facts were last updated.
 */
final class PhabricatorFactLastUpdatedEngine extends PhabricatorFactEngine {

  public function getFactSpecs(array $fact_types) {
    $results = array();
    foreach ($fact_types as $type) {
      if ($type == 'updated') {
        $results[] = id(new PhabricatorFactSimpleSpec($type))
          ->setName(pht('Facts Last Updated'))
          ->setUnit(PhabricatorFactSimpleSpec::UNIT_EPOCH);
      }
    }
    return $results;
  }

  public function shouldComputeAggregateFacts() {
    return true;
  }

  public function computeAggregateFacts() {
    $facts = array();

    $facts[] = id(new PhabricatorFactAggregate())
      ->setFactType('updated')
      ->setValueX(time());

    return $facts;
  }

}
