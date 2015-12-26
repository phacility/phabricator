<?php

final class PhabricatorSearchIndexVersionDestructionEngineExtension
  extends PhabricatorDestructionEngineExtension {

  const EXTENSIONKEY = 'search.index.version';

  public function getExtensionName() {
    return pht('Search Index Versions');
  }

  public function destroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {

    $table = new PhabricatorSearchIndexVersion();

    queryfx(
      $table->establishConnection('w'),
      'DELETE FROM %T WHERE objectPHID = %s',
      $table->getTableName(),
      $object->getPHID());
  }

}
