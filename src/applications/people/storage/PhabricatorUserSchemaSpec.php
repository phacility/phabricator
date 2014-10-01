<?php

final class PhabricatorUserSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorUserDAO');

    $this->buildEdgeSchemata(new PhabricatorUser());

    $this->buildTransactionSchema(
      new PhabricatorUserTransaction());

    $this->buildCustomFieldSchemata(
      new PhabricatorUserConfiguredCustomFieldStorage(),
      array(
        new PhabricatorUserCustomFieldNumericIndex(),
        new PhabricatorUserCustomFieldStringIndex(),
      ));

    $this->buildRawSchema(
      id(new PhabricatorUser())->getApplicationName(),
      PhabricatorUser::NAMETOKEN_TABLE,
      array(
        'token' => 'text255',
        'userID' => 'id',
      ),
      array(
        'token' => array(
          'columns' => array('token'),
        ),
        'userID' => array(
          'columns' => array('userID'),
        ),
      ));

  }

}
