<?php

final class PhabricatorAuthTemporaryTokenRevoker
  extends PhabricatorAuthRevoker {

  const REVOKERKEY = 'temporary';

  public function revokeAllCredentials() {
    $table = new PhabricatorAuthTemporaryToken();
    $conn = $table->establishConnection('w');

    queryfx(
      $conn,
      'DELETE FROM %T',
      $table->getTableName());

    return $conn->getAffectedRows();
  }

  public function revokeCredentialsFrom($object) {
    $table = new PhabricatorAuthTemporaryToken();
    $conn = $table->establishConnection('w');

    queryfx(
      $conn,
      'DELETE FROM %T WHERE tokenResource = %s',
      $table->getTableName(),
      $object->getPHID());

    return $conn->getAffectedRows();
  }

}
