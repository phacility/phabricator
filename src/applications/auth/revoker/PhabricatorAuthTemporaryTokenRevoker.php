<?php

final class PhabricatorAuthTemporaryTokenRevoker
  extends PhabricatorAuthRevoker {

  const REVOKERKEY = 'temporary';

  public function getRevokerName() {
    return pht('Temporary Tokens');
  }

  public function getRevokerDescription() {
    return pht(
      "Revokes temporary authentication tokens.\n\n".
      "Temporary tokens are used in password reset mail, welcome mail, and ".
      "by some other systems like Git LFS. Revoking temporary tokens will ".
      "invalidate existing links in password reset and invite mail that ".
      "was sent before the revocation occurred.");
  }

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
