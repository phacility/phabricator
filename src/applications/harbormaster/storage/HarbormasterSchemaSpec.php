<?php

final class HarbormasterSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new HarbormasterBuildable());

    // NOTE: This table is not used by any Harbormaster objects, but is used
    // by unit tests.
    $this->buildRawSchema(
      id(new HarbormasterObject())->getApplicationName(),
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


    $this->buildRawSchema(
      id(new HarbormasterBuildable())->getApplicationName(),
      'harbormaster_buildlogchunk',
      array(
        'id' => 'auto',
        'logID' => 'id',
        'encoding' => 'text32',

        // T6203/NULLABILITY
        // Both the type and nullability of this column are crazily wrong.
        'size' => 'uint32?',

        'chunk' => 'bytes',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('id'),
          'unique' => true,
        ),
        'key_log' => array(
          'columns' => array('logID'),
        ),
      ));

  }

}
