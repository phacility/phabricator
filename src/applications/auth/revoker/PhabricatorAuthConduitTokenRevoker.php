<?php

final class PhabricatorAuthConduitTokenRevoker
  extends PhabricatorAuthRevoker {

  const REVOKERKEY = 'conduit';

  public function getRevokerName() {
    return pht('Conduit API Tokens');
  }

  public function getRevokerDescription() {
    return pht(
      "Revokes all Conduit API tokens used to access the API.\n\n".
      "Users will need to use `arc install-certificate` to install new ".
      "API tokens before `arc` commands will work. Bots and scripts which ".
      "access the API will need to have new tokens generated and ".
      "installed.");
  }

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
