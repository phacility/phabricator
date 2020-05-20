<?php

final class DifferentialSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new DifferentialRevision());

    $this->buildRawSchema(
      id(new DifferentialRevision())->getApplicationName(),
      DifferentialChangeset::TABLE_CACHE,
      array(
        'id' => 'auto',
        'cacheIndex' => 'bytes12',
        'cache' => 'bytes',
        'dateCreated' => 'epoch',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('id'),
          'unique' => true,
        ),
        'key_cacheIndex' => array(
          'columns' => array('cacheIndex'),
          'unique' => true,
        ),
        'key_created' => array(
          'columns' => array('dateCreated'),
        ),
      ),
      array(
        'persistence' => PhabricatorConfigTableSchema::PERSISTENCE_CACHE,
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
