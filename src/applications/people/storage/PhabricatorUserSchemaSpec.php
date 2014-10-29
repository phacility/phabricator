<?php

final class PhabricatorUserSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorUser());

    $this->buildRawSchema(
      id(new PhabricatorUser())->getApplicationName(),
      PhabricatorUser::NAMETOKEN_TABLE,
      array(
        'token' => 'sort255',
        'userID' => 'id',
      ),
      array(
        'token' => array(
          'columns' => array('token(128)'),
        ),
        'userID' => array(
          'columns' => array('userID'),
        ),
      ));

  }

}
