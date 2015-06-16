<?php

/**
 * Simple fact engine which counts objects.
 */
final class PhabricatorFactCountEngine extends PhabricatorFactEngine {

  public function getFactSpecs(array $fact_types) {
    $results = array();
    foreach ($fact_types as $type) {
      if (!strncmp($type, '+N:', 3)) {
        if ($type == '+N:*') {
          $name = pht('Total Objects');
        } else {
          $name = pht('Total Objects of type %s', substr($type, 3));
        }

        $results[] = id(new PhabricatorFactSimpleSpec($type))
          ->setName($name)
          ->setUnit(PhabricatorFactSimpleSpec::UNIT_COUNT);
      }

      if (!strncmp($type, 'N:', 2)) {
        if ($type == 'N:*') {
          $name = pht('Objects');
        } else {
          $name = pht('Objects of type %s', substr($type, 2));
        }
        $results[] = id(new PhabricatorFactSimpleSpec($type))
          ->setName($name)
          ->setUnit(PhabricatorFactSimpleSpec::UNIT_COUNT);
      }

    }
    return $results;
  }

  public function shouldComputeRawFactsForObject(PhabricatorLiskDAO $object) {
    return true;
  }

  public function computeRawFactsForObject(PhabricatorLiskDAO $object) {
    $facts = array();

    $phid = $object->getPHID();
    $type = phid_get_type($phid);

    foreach (array('N:*', 'N:'.$type) as $fact_type) {
      $facts[] = id(new PhabricatorFactRaw())
        ->setFactType($fact_type)
        ->setObjectPHID($phid)
        ->setValueX(1)
        ->setEpoch($object->getDateCreated());
    }

    return $facts;
  }

  public function shouldComputeAggregateFacts() {
    return true;
  }

  public function computeAggregateFacts() {
    $table = new PhabricatorFactRaw();
    $table_name = $table->getTableName();
    $conn = $table->establishConnection('r');

    $counts = queryfx_all(
      $conn,
      'SELECT factType, SUM(valueX) N FROM %T WHERE factType LIKE %>
        GROUP BY factType',
      $table_name,
      'N:');

    $facts = array();
    foreach ($counts as $count) {
      $facts[] = id(new PhabricatorFactAggregate())
        ->setFactType('+'.$count['factType'])
        ->setValueX($count['N']);
    }

    return $facts;
  }


}
