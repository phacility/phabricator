<?php

final class PhabricatorFactDaemon extends PhabricatorDaemon {

  private $engines;

  protected function run() {
    $this->setEngines(PhabricatorFactEngine::loadAllEngines());
    do {
      PhabricatorCaches::destroyRequestCache();

      $iterators = $this->getAllApplicationIterators();
      foreach ($iterators as $iterator_name => $iterator) {
        $this->processIteratorWithCursor($iterator_name, $iterator);
      }

      $sleep_duration = 60;

      if ($this->shouldHibernate($sleep_duration)) {
        break;
      }

      $this->sleep($sleep_duration);
    } while (!$this->shouldExit());
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

    $viewer = PhabricatorUser::getOmnipotentUser();
    foreach ($engines as $engine) {
      $engine->setViewer($viewer);
    }

    $this->engines = $engines;
    return $this;
  }

  public function processIterator($iterator) {
    $result = null;

    $datapoints = array();
    $count = 0;
    foreach ($iterator as $key => $object) {
      $phid = $object->getPHID();
      $this->log(pht('Processing %s...', $phid));
      $object_datapoints = $this->newDatapoints($object);
      $count += count($object_datapoints);

      $datapoints[$phid] = $object_datapoints;

      if ($count > 1024) {
        $this->updateDatapoints($datapoints);
        $datapoints = array();
        $count = 0;
      }

      $result = $key;
    }

    if ($count) {
      $this->updateDatapoints($datapoints);
      $datapoints = array();
      $count = 0;
    }

    return $result;
  }

  private function newDatapoints(PhabricatorLiskDAO $object) {
    $facts = array();
    foreach ($this->engines as $engine) {
      if (!$engine->supportsDatapointsForObject($object)) {
        continue;
      }
      $facts[] = $engine->newDatapointsForObject($object);
    }

    return array_mergev($facts);
  }

  private function updateDatapoints(array $map) {
    foreach ($map as $phid => $facts) {
      assert_instances_of($facts, 'PhabricatorFactIntDatapoint');
    }

    $phids = array_keys($map);
    if (!$phids) {
      return;
    }

    $fact_keys = array();
    $objects = array();
    foreach ($map as $phid => $facts) {
      foreach ($facts as $fact) {
        $fact_keys[$fact->getKey()] = true;

        $object_phid = $fact->getObjectPHID();
        $objects[$object_phid] = $object_phid;

        $dimension_phid = $fact->getDimensionPHID();
        if ($dimension_phid !== null) {
          $objects[$dimension_phid] = $dimension_phid;
        }
      }
    }

    $key_map = id(new PhabricatorFactKeyDimension())
      ->newDimensionMap(array_keys($fact_keys), true);
    $object_map = id(new PhabricatorFactObjectDimension())
      ->newDimensionMap(array_keys($objects), true);

    $table = new PhabricatorFactIntDatapoint();
    $conn = $table->establishConnection('w');
    $table_name = $table->getTableName();

    $sql = array();
    foreach ($map as $phid => $facts) {
      foreach ($facts as $fact) {
        $key_id = $key_map[$fact->getKey()];
        $object_id = $object_map[$fact->getObjectPHID()];

        $dimension_phid = $fact->getDimensionPHID();
        if ($dimension_phid !== null) {
          $dimension_id = $object_map[$dimension_phid];
        } else {
          $dimension_id = null;
        }

        $sql[] = qsprintf(
          $conn,
          '(%d, %d, %nd, %d, %d)',
          $key_id,
          $object_id,
          $dimension_id,
          $fact->getValue(),
          $fact->getEpoch());
      }
    }

    $rebuilt_ids = array_select_keys($object_map, $phids);

    $table->openTransaction();

      queryfx(
        $conn,
        'DELETE FROM %T WHERE objectID IN (%Ld)',
        $table_name,
        $rebuilt_ids);

      if ($sql) {
        foreach (PhabricatorLiskDAO::chunkSQL($sql) as $chunk) {
          queryfx(
            $conn,
            'INSERT INTO %T
              (keyID, objectID, dimensionID, value, epoch)
              VALUES %LQ',
            $table_name,
            $chunk);
        }
      }

    $table->saveTransaction();
  }

}
