<?php

final class DifferentialSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new DifferentialRevision());

    $this->buildRawSchema(
      id(new DifferentialRevision())->getApplicationName(),
      DifferentialChangeset::TABLE_CACHE,
      array(
        'id' => 'id',
        'cache' => 'bytes',
        'dateCreated' => 'epoch',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('id'),
          'unique' => true,
        ),
        'dateCreated' => array(
          'columns' => array('dateCreated'),
        ),
      ),
      array(
        'persistence' => PhabricatorConfigTableSchema::PERSISTENCE_CACHE,
      ));

    // TODO: All readers and writers for this table were removed in April
    // 2019. Destroy this table once we're sure we won't miss it.

    $this->buildRawSchema(
      id(new DifferentialRevision())->getApplicationName(),
      'differential_commit',
      array(
        'revisionID' => 'id',
        'commitPHID' => 'phid',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('revisionID', 'commitPHID'),
          'unique' => true,
        ),
        'commitPHID' => array(
          'columns' => array('commitPHID'),
          'unique' => true,
        ),
      ));

    $this->buildRawSchema(
      id(new DifferentialRevision())->getApplicationName(),
      ArcanistDifferentialRevisionHash::TABLE_NAME,
      array(
        'revisionID' => 'id',
        'type' => 'bytes4',
        'hash' => 'bytes40',
      ),
      array(
        'type' => array(
          'columns' => array('type', 'hash'),
        ),
        'revisionID' => array(
          'columns' => array('revisionID'),
        ),
      ));


  }

}
