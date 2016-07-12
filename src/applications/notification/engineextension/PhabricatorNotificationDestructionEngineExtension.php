<?php

final class PhabricatorNotificationDestructionEngineExtension
  extends PhabricatorDestructionEngineExtension {

  const EXTENSIONKEY = 'notifications';

  public function getExtensionName() {
    return pht('Notifications');
  }

  public function destroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {

    $table = new PhabricatorFeedStoryNotification();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE primaryObjectPHID = %s',
      $table->getTableName(),
      $object->getPHID());
  }

}
