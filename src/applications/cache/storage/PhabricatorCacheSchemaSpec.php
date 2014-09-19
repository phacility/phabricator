<?php

final class PhabricatorCacheSchemaSpec extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('PhabricatorCacheDAO');

    $this->buildRawSchema(
      'cache',
      id(new PhabricatorKeyValueDatabaseCache())->getTableName(),
      array(
        'id' => 'id64',
        'cacheKeyHash' => 'bytes12',
        'cacheKey' => 'text128',
        'cacheFormat' => 'text16',
        'cacheData' => 'bytes',
        'cacheCreated' => 'epoch',
        'cacheExpires' => 'epoch?',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('id'),
        ),
        'key_cacheKeyHash' => array(
          'columns' => array('cacheKeyHash'),
        ),
      ));

  }

}
