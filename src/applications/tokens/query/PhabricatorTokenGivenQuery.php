<?php

final class PhabricatorTokenGivenQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $authorPHIDs;
  private $objectPHIDs;
  private $tokenPHIDs;

  public function withTokenPHIDs(array $token_phids) {
    $this->tokenPHIDs = $token_phids;
    return $this;
  }

  public function withObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function withAuthorPHIDs(array $author_phids) {
    $this->authorPHIDs = $author_phids;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorTokenGiven();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->tokenPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'tokenPHID IN (%Ls)',
        $this->tokenPHIDs);
    }

    return $where;
  }

  protected function willFilterPage(array $results) {
    $object_phids = mpull($results, 'getObjectPHID');

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($object_phids)
      ->execute();
    $objects = mpull($objects, null, 'getPHID');

    foreach ($results as $key => $result) {
      $object = idx($objects, $result->getObjectPHID());

      if ($object) {
        if ($object instanceof PhabricatorTokenReceiverInterface) {
          $result->attachObject($object);
          continue;
        }
      }

      $this->didRejectResult($result);
      unset($results[$key]);
    }

    return $results;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorTokensApplication';
  }

}
