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

  protected function loadPage() {
    $table = new PhabricatorTokenGiven();
    $conn_r = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($rows);
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->authorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->objectPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    if ($this->tokenPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'tokenPHID IN (%Ls)',
        $this->tokenPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  protected function willFilterPage(array $results) {
    $object_phids = array_filter(mpull($results, 'getObjectPHID'));
    if (!$object_phids) {
      return array();
    }

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($object_phids)
      ->execute();

    foreach ($results as $key => $result) {
      $phid = $result->getObjectPHID();
      if (empty($objects[$phid])) {
        unset($results[$key]);
      } else {
        $result->attachObject($objects[$phid]);
      }
    }

    return $results;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorTokensApplication';
  }

}
