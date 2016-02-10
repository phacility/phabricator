<?php

final class HeraldSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildRawSchema(
      id(new HeraldRule())->getApplicationName(),
      HeraldRule::TABLE_RULE_APPLIED,
      array(
        'ruleID' => 'id',
        'phid' => 'phid',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('ruleID', 'phid'),
          'unique' => true,
        ),
        'phid' => array(
          'columns' => array('phid'),
        ),
      ));

    $this->buildRawSchema(
      id(new HeraldRule())->getApplicationName(),
      HeraldTranscript::TABLE_SAVED_HEADER,
      array(
        'phid' => 'phid',
        'header' => 'text',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
      ));
    $this->buildEdgeSchemata(new HeraldRule());
  }

}
