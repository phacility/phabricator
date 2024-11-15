<?php

final class PhabricatorRoleSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorRole());

    $this->buildRawSchema(
      id(new PhabricatorRole())->getApplicationName(),
      PhabricatorRole::TABLE_DATASOURCE_TOKEN,
      array(
        'id' => 'auto',
        'roleID' => 'id',
        'token' => 'text128',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('id'),
          'unique' => true,
        ),
        'token' => array(
          'columns' => array('token', 'roleID'),
          'unique' => true,
        ),
        'roleID' => array(
          'columns' => array('roleID'),
        ),
      ));


  }

}
