<?php

final class PhabricatorProjectSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorProjectDAO');

    $this->buildEdgeSchemata(new PhabricatorProject());

    $this->buildTransactionSchema(
      new PhabricatorProjectTransaction());

    $this->buildCustomFieldSchemata(
      new PhabricatorProjectCustomFieldStorage(),
      array(
        new PhabricatorProjectCustomFieldNumericIndex(),
        new PhabricatorProjectCustomFieldStringIndex(),
      ));

    $this->buildTransactionSchema(
      new PhabricatorProjectColumnTransaction());

    $this->buildRawSchema(
      id(new PhabricatorProject())->getApplicationName(),
      PhabricatorProject::TABLE_DATASOURCE_TOKEN,
      array(
        'id' => 'auto',
        'projectID' => 'id',
        'token' => 'text128',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('id'),
          'unique' => true,
        ),
        'token' => array(
          'columns' => array('token', 'projectID'),
          'unique' => true,
        ),
        'projectID' => array(
          'columns' => array('projectID'),
        ),
      ));


  }

}
