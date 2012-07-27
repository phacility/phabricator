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

final class PhabricatorFactDaemon extends PhabricatorDaemon {

  private $engines;

  const RAW_FACT_BUFFER_LIMIT = 128;

  public function run() {
    throw new Exception("This daemon doesn't do anything yet!");
  }

  public function setEngines(array $engines) {
    assert_instances_of($engines, 'PhabricatorFactEngine');

    $this->engines = $engines;
    return $this;
  }

  public function processIterator($iterator) {
    $result = null;

    $raw_facts = array();
    foreach ($iterator as $key => $object) {
      $raw_facts[$object->getPHID()] = $this->computeRawFacts($object);
      if (count($raw_facts) > self::RAW_FACT_BUFFER_LIMIT) {
        $this->updateRawFacts($raw_facts);
        $raw_facts = array();
      }
      $result = $key;
    }

    if ($raw_facts) {
      $this->updateRawFacts($raw_facts);
      $raw_facts = array();
    }

    return $result;
  }

  private function computeRawFacts(PhabricatorLiskDAO $object) {
    $facts = array();
    foreach ($this->engines as $engine) {
      if (!$engine->shouldComputeRawFactsForObject($object)) {
        continue;
      }
      $facts[] = $engine->computeRawFactsForObject($object);
    }

    return array_mergev($facts);
  }

  private function updateRawFacts(array $map) {
    foreach ($map as $phid => $facts) {
      assert_instances_of($facts, 'PhabricatorFactRaw');
    }

    $phids = array_keys($map);
    if (!$phids) {
      return;
    }

    $table = new PhabricatorFactRaw();
    $conn = $table->establishConnection('w');
    $table_name = $table->getTableName();

    $sql = array();
    foreach ($map as $phid => $facts) {
      foreach ($facts as $fact) {
        $sql[] = qsprintf(
          $conn,
          '(%s, %s, %s, %d, %d, %d)',
          $fact->getFactType(),
          $fact->getObjectPHID(),
          $fact->getObjectA(),
          $fact->getValueX(),
          $fact->getValueY(),
          $fact->getEpoch());
      }
    }

    $table->openTransaction();

      queryfx(
        $conn,
        'DELETE FROM %T WHERE objectPHID IN (%Ls)',
        $table_name,
        $phids);

      if ($sql) {
        foreach (array_chunk($sql, 256) as $chunk) {
          queryfx(
            $conn,
            'INSERT INTO %T
              (factType, objectPHID, objectA, valueX, valueY, epoch)
              VALUES %Q',
            $table_name,
            implode(', ', $chunk));
        }
      }

    $table->saveTransaction();
  }

}
