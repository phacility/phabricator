<?php

final class PhabricatorAuthConduitTokenRevoker
  extends PhabricatorAuthRevoker {

  const REVOKERKEY = 'conduit';

  public function revokeAllCredentials() {
    $table = id(new PhabricatorConduitToken());
    $conn = $table->establishConnection('w');

    queryfx(
      $conn,
      'DELETE FROM %T',
      $table->getTableName());

    return $conn->getAffectedRows();
  }

  public function revokeCredentialsFrom($object) {
    $table = id(new PhabricatorConduitToken());
    $conn = $table->establishConnection('w');

    queryfx(
      $conn,
      'DELETE FROM %T WHERE objectPHID = %s',
      $table->getTableName(),
      $object->getPHID());

    return $conn->getAffectedRows();
  }

}
