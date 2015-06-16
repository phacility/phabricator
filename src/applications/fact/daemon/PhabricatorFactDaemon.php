<?php

final class PhabricatorFactDaemon extends PhabricatorDaemon {

  private $engines;

  const RAW_FACT_BUFFER_LIMIT = 128;

  protected function run() {
    $this->setEngines(PhabricatorFactEngine::loadAllEngines());
    while (!$this->shouldExit()) {
      PhabricatorCaches::destroyRequestCache();

      $iterators = $this->getAllApplicationIterators();
      foreach ($iterators as $iterator_name => $iterator) {
        $this->processIteratorWithCursor($iterator_name, $iterator);
      }
      $this->processAggregates();

      $this->log(pht('Zzz...'));
      $this->sleep(60 * 5);
    }
  }

  public static function getAllApplicationIterators() {
    $apps = PhabricatorApplication::getAllInstalledApplications();

    $iterators = array();
    foreach ($apps as $app) {
      foreach ($app->getFactObjectsForAnalysis() as $object) {
        $iterator = new PhabricatorFactUpdateIterator($object);
        $iterators[get_class($object)] = $iterator;
      }
    }

    return $iterators;
  }

  public function processIteratorWithCursor($iterator_name, $iterator) {
    $this->log(pht("Processing cursor '%s'.", $iterator_name));

    $cursor = id(new PhabricatorFactCursor())->loadOneWhere(
      'name = %s',
      $iterator_name);
    if (!$cursor) {
      $cursor = new PhabricatorFactCursor();
      $cursor->setName($iterator_name);
      $position = null;
    } else {
      $position = $cursor->getPosition();
    }

    if ($position) {
      $iterator->setPosition($position);
    }

    $new_cursor_position = $this->processIterator($iterator);

    if ($new_cursor_position) {
      $cursor->setPosition($new_cursor_position);
      $cursor->save();
    }
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
      $phid = $object->getPHID();
      $this->log(pht('Processing %s...', $phid));
      $raw_facts[$phid] = $this->computeRawFacts($object);
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

  public function processAggregates() {
    $this->log(pht('Processing aggregates.'));

    $facts = $this->computeAggregateFacts();
    $this->updateAggregateFacts($facts);
  }

  private function computeAggregateFacts() {
    $facts = array();
    foreach ($this->engines as $engine) {
      if (!$engine->shouldComputeAggregateFacts()) {
        continue;
      }
      $facts[] = $engine->computeAggregateFacts();
    }
    return array_mergev($facts);
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

  private function updateAggregateFacts(array $facts) {
    if (!$facts) {
      return;
    }

    $table = new PhabricatorFactAggregate();
    $conn = $table->establishConnection('w');
    $table_name = $table->getTableName();

    $sql = array();
    foreach ($facts as $fact) {
      $sql[] = qsprintf(
        $conn,
        '(%s, %s, %d)',
        $fact->getFactType(),
        $fact->getObjectPHID(),
        $fact->getValueX());
    }

    foreach (array_chunk($sql, 256) as $chunk) {
      queryfx(
        $conn,
        'INSERT INTO %T (factType, objectPHID, valueX) VALUES %Q
          ON DUPLICATE KEY UPDATE valueX = VALUES(valueX)',
        $table_name,
        implode(', ', $chunk));
    }

  }

}
