<?php

final class PhabricatorAuthSessionRevoker
  extends PhabricatorAuthRevoker {

  const REVOKERKEY = 'session';

  public function revokeAllCredentials() {
    $table = new PhabricatorAuthSession();
    $conn = $table->establishConnection('w');

    queryfx(
      $conn,
      'DELETE FROM %T',
      $table->getTableName());

    return $conn->getAffectedRows();
  }

  public function revokeCredentialsFrom($object) {
    $table = new PhabricatorAuthSession();
    $conn = $table->establishConnection('w');

    queryfx(
      $conn,
      'DELETE FROM %T WHERE userPHID = %s',
      $table->getTableName(),
      $object->getPHID());

    return $conn->getAffectedRows();
  }

}
