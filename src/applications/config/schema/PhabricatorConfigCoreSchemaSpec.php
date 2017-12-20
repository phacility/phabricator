<?php

/**
 * Builds schemata definitions for core infrastructure.
 */
final class PhabricatorConfigCoreSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    // Build all Lisk table schemata.

    $lisk_objects = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorLiskDAO')
      ->execute();

    $counters = array();
    foreach ($lisk_objects as $object) {
      if ($object->getConfigOption(LiskDAO::CONFIG_NO_TABLE)) {
        continue;
      }
      $this->buildLiskObjectSchema($object);

      $ids_counter = LiskDAO::IDS_COUNTER;
      if ($object->getConfigOption(LiskDAO::CONFIG_IDS) == $ids_counter) {
        $counters[$object->getApplicationName()] = true;
      }
    }

    foreach ($counters as $database => $ignored) {
      $this->buildRawSchema(
        $database,
        PhabricatorLiskDAO::COUNTER_TABLE_NAME,
        array(
          'counterName' => 'text32',
          'counterValue' => 'id64',
        ),
        array(
          'PRIMARY' => array(
            'columns' => array('counterName'),
            'unique' => true,
          ),
        ));
    }

    $ferret_objects = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorFerretInterface')
      ->execute();

    foreach ($ferret_objects as $ferret_object) {
      $engine = $ferret_object->newFerretEngine();
      $this->buildFerretIndexSchema($engine);
    }
  }
}
