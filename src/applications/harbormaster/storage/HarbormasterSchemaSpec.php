<?php

final class HarbormasterSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('HarbormasterDAO');

    $this->buildEdgeSchemata(new HarbormasterBuildable());
    $this->buildCounterSchema(new HarbormasterBuildable());

    $this->buildTransactionSchema(
      new HarbormasterBuildableTransaction());

    $this->buildTransactionSchema(
      new HarbormasterBuildTransaction());

    $this->buildTransactionSchema(
      new HarbormasterBuildPlanTransaction());

    $this->buildTransactionSchema(
      new HarbormasterBuildStepTransaction());

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
