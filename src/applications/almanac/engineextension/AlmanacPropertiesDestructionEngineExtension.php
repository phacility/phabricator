<?php

final class AlmanacPropertiesDestructionEngineExtension
  extends PhabricatorDestructionEngineExtension {

  const EXTENSIONKEY = 'almanac.properties';

  public function getExtensionName() {
    return pht('Almanac Properties');
  }

  public function canDestroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {
    return ($object instanceof AlmanacPropertyInterface);
  }

  public function destroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {

    $table = new AlmanacProperty();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE objectPHID = %s',
      $table->getTableName(),
      $object->getPHID());
  }

}
