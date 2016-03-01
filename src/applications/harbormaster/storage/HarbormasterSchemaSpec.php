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

  }

}
