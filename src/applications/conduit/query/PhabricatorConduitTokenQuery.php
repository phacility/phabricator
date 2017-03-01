<?php

final class PhabricatorConduitTokenQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $objectPHIDs;
  private $expired;
  private $tokens;
  private $tokenTypes;

  public function withExpired($expired) {
    $this->expired = $expired;
    return $this;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withObjectPHIDs(array $phids) {
    $this->objectPHIDs = $phids;
    return $this;
  }

  public function withTokens(array $tokens) {
    $this->tokens = $tokens;
    return $this;
  }

  public function withTokenTypes(array $types) {
    $this->tokenTypes = $types;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorConduitToken();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->tokens !== null) {
      $where[] = qsprintf(
        $conn,
        'token IN (%Ls)',
        $this->tokens);
    }

    if ($this->tokenTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'tokenType IN (%Ls)',
        $this->tokenTypes);
    }

    if ($this->expired !== null) {
      if ($this->expired) {
        $where[] = qsprintf(
          $conn,
          'expires <= %d',
          PhabricatorTime::getNow());
      } else {
        $where[] = qsprintf(
          $conn,
          'expires IS NULL OR expires > %d',
          PhabricatorTime::getNow());
      }
    }

    return $where;
  }

  protected function willFilterPage(array $tokens) {
    $object_phids = mpull($tokens, 'getObjectPHID');
    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($object_phids)
      ->execute();
    $objects = mpull($objects, null, 'getPHID');

    foreach ($tokens as $key => $token) {
      $object = idx($objects, $token->getObjectPHID(), null);
      if (!$object) {
        $this->didRejectResult($token);
        unset($tokens[$key]);
        continue;
      }
      $token->attachObject($object);
    }

    return $tokens;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorConduitApplication';
  }

}
