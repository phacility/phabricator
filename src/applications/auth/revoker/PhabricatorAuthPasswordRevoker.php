<?php

final class PhabricatorAuthPasswordRevoker
  extends PhabricatorAuthRevoker {

  const REVOKERKEY = 'password';

  public function revokeAllCredentials() {
    $query = new PhabricatorAuthPasswordQuery();
    return $this->revokeWithQuery($query);
  }

  public function revokeCredentialsFrom($object) {
    $query = id(new PhabricatorAuthPasswordQuery())
      ->withObjectPHIDs(array($object->getPHID()));
    return $this->revokeWithQuery($query);
  }

  private function revokeWithQuery(PhabricatorAuthPasswordQuery $query) {
    $viewer = $this->getViewer();

    $passwords = $query
      ->setViewer($viewer)
      ->withIsRevoked(false)
      ->execute();

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorDaemonContentSource::SOURCECONST);

    $revoke_type = PhabricatorAuthPasswordRevokeTransaction::TRANSACTIONTYPE;

    $auth_phid = id(new PhabricatorAuthApplication())->getPHID();
    foreach ($passwords as $password) {
      $xactions = array();

      $xactions[] = $password->getApplicationTransactionTemplate()
        ->setTransactionType($revoke_type)
        ->setNewValue(true);

      $editor = $password->getApplicationTransactionEditor()
        ->setActor($viewer)
        ->setActingAsPHID($auth_phid)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setContentSource($content_source)
        ->applyTransactions($password, $xactions);
    }

    return count($passwords);
  }

}
