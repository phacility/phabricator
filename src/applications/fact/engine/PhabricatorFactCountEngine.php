<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Simple fact engine which counts objects.
 */
final class PhabricatorFactCountEngine extends PhabricatorFactEngine {

  public function getFactSpecs(array $fact_types) {
    $results = array();
    foreach ($fact_types as $type) {
      if (!strncmp($type, '+N:', 3)) {
        if ($type == '+N:*') {
          $name = 'Total Objects';
        } else {
          $name = 'Total Objects of type '.substr($type, 3);
        }

        $results[] = id(new PhabricatorFactSimpleSpec($type))
          ->setName($name)
          ->setUnit(PhabricatorFactSimpleSpec::UNIT_COUNT);
      }

      if (!strncmp($type, 'N:', 2)) {
        if ($type == 'N:*') {
          $name = 'Objects';
        } else {
          $name = 'Objects of type '.substr($type, 2);
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
