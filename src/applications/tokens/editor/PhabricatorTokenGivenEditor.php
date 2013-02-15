<?php

final class PhabricatorTokenGivenEditor
  extends PhabricatorEditor {

  public function addToken($object_phid, $token_phid) {
    $token = $this->validateToken($token_phid);
    $handle = $this->validateObject($object_phid);

    $actor = $this->requireActor();

    $token_given = id(new PhabricatorTokenGiven())
      ->setAuthorPHID($actor->getPHID())
      ->setObjectPHID($handle->getPHID())
      ->setTokenPHID($token->getPHID());

    $token_given->openTransaction();

      $this->executeDeleteToken($handle);

      $token_given->save();

      queryfx(
        $token_given->establishConnection('w'),
        'INSERT INTO %T (objectPHID, tokenCount) VALUES (%s, 1)
          ON DUPLICATE KEY UPDATE tokenCount = tokenCount + 1',
        id(new PhabricatorTokenCount())->getTableName(),
        $handle->getPHID());

    $token_given->saveTransaction();

    return $token_given;
  }

  public function deleteToken($object_phid) {
    $handle = $this->validateObject($object_phid);

    return $this->executeDeleteToken($handle);
  }

  private function executeDeleteToken(PhabricatorObjectHandle $handle) {
    $actor = $this->requireActor();

    $token_given = id(new PhabricatorTokenGiven())->loadOneWhere(
      'authorPHID = %s AND objectPHID = %s',
      $actor->getPHID(),
      $handle->getPHID());
    if (!$token_given) {
      return;
    }

    $token_given->openTransaction();

      $token_given->delete();

      queryfx(
        $token_given->establishConnection('w'),
        'INSERT INTO %T (objectPHID, tokenCount) VALUES (%s, 0)
          ON DUPLICATE KEY UPDATE tokenCount = tokenCount - 1',
        id(new PhabricatorTokenCount())->getTableName(),
        $handle->getPHID());

    $token_given->saveTransaction();
  }

  private function validateToken($token_phid) {
    $tokens = id(new PhabricatorTokenQuery())
      ->setViewer($this->requireActor())
      ->withPHIDs(array($token_phid))
      ->execute();

    if (empty($tokens)) {
      throw new Exception("No such token!");
    }

    return head($tokens);
  }

  private function validateObject($object_phid) {
    $handle = PhabricatorObjectHandleData::loadOneHandle(
      $object_phid,
      $this->requireActor());

    if (!$handle->isComplete()) {
      throw new Exception("No such object!");
    }

    return $handle;
  }

}
